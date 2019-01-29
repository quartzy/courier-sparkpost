<?php

declare(strict_types=1);

namespace Courier\SparkPost;

use Courier\ConfirmingCourier;
use Courier\Exceptions\TransmissionException;
use Courier\Exceptions\UnsupportedContentException;
use Courier\SavesReceipts;
use PhpEmail\Address;
use PhpEmail\Content;
use PhpEmail\Email;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SparkPost\SparkPost;
use SparkPost\SparkPostException;
use SparkPost\SparkPostResponse;

/**
 * A courier implementation using SparkPost as the third-party provider. This library uses the web API and the
 * php-sparkpost library to send transmissions.
 *
 * An important note is that while the SparkPost API does not support sending attachments on templated transmissions,
 * this API simulates the feature by creating an inline template based on the defined template using the API. In this
 * case, all template variables will be sent as expected, but tracking/reporting may not work as expected within
 * SparkPost.
 */
class SparkPostCourier implements ConfirmingCourier
{
    use SavesReceipts;

    const RECIPIENTS        = 'recipients';
    const CC                = 'cc';
    const BCC               = 'bcc';
    const REPLY_TO          = 'reply_to';
    const SUBSTITUTION_DATA = 'substitution_data';

    const CONTENT       = 'content';
    const FROM          = 'from';
    const SUBJECT       = 'subject';
    const HTML          = 'html';
    const TEXT          = 'text';
    const INLINE_IMAGES = 'inline_images';
    const ATTACHMENTS   = 'attachments';
    const TEMPLATE_ID   = 'template_id';

    const HEADERS   = 'headers';
    const CC_HEADER = 'CC';

    const ADDRESS       = 'address';
    const CONTACT_NAME  = 'name';
    const CONTACT_EMAIL = 'email';
    const HEADER_TO     = 'header_to';

    const ATTACHMENT_NAME = 'name';
    const ATTACHMENT_TYPE = 'type';
    const ATTACHMENT_DATA = 'data';

    /**
     * @var SparkPost
     */
    private $sparkPost;

    /**
     * @var SparkPostTemplates
     */
    private $templates;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param SparkPost       $sparkPost
     * @param LoggerInterface $logger
     */
    public function __construct(SparkPost $sparkPost, LoggerInterface $logger = null)
    {
        $this->sparkPost = $sparkPost;
        $this->logger    = $logger ?: new NullLogger();
        $this->templates = new SparkPostTemplates($sparkPost, $this->logger);
    }

    public function deliver(Email $email): void
    {
        if (!$this->supportsContent($email->getContent())) {
            throw new UnsupportedContentException($email->getContent());
        }

        $mail = $this->prepareEmail($email);
        $mail = $this->prepareContent($email, $mail);

        $response = $this->send($mail);

        $this->saveReceipt($email, $response->getBody()['results']['id']);
    }

    /**
     * @param array $mail
     *
     * @return SparkPostResponse
     */
    protected function send(array $mail): SparkPostResponse
    {
        $promise = $this->sparkPost->transmissions->post($mail);

        try {
            return $promise->wait();
        } catch (SparkPostException $e) {
            $this->logger->error(
                'Received status {code} from SparkPost with body: {body}',
                [
                    'code' => $e->getCode(),
                    'body' => $e->getBody(),
                ]
            );

            throw new TransmissionException($e->getCode(), $e);
        }
    }

    /**
     * @return array
     */
    protected function supportedContent(): array
    {
        return [
            Content\Contracts\SimpleContent::class,
            Content\Contracts\TemplatedContent::class,
        ];
    }

    /**
     * Determine if the content is supported by this courier.
     *
     * @param Content $content
     *
     * @return bool
     */
    protected function supportsContent(Content $content): bool
    {
        foreach ($this->supportedContent() as $contentType) {
            if ($content instanceof $contentType) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param Email $email
     *
     * @return array
     */
    protected function prepareEmail(Email $email): array
    {
        $message  = [];
        $headerTo = $this->buildHeaderTo($email);

        $message[self::RECIPIENTS] = [];

        foreach ($email->getToRecipients() as $recipient) {
            $message[self::RECIPIENTS][] = $this->createAddress($recipient, $headerTo);
        }

        foreach ($email->getCcRecipients() as $recipient) {
            $message[self::RECIPIENTS][] = $this->createAddress($recipient, $headerTo);
        }

        foreach ($email->getBccRecipients() as $recipient) {
            $message[self::RECIPIENTS][] = $this->createAddress($recipient, $headerTo);
        }

        return $message;
    }

    /**
     * @param Email $email
     * @param array $message
     *
     * @return array
     */
    protected function prepareContent(Email $email, array $message): array
    {
        switch (true) {
            case $email->getContent() instanceof Content\Contracts\TemplatedContent:
                $message[self::CONTENT]           = $this->buildTemplateContent($email);
                $message[self::SUBSTITUTION_DATA] = $this->buildTemplateData($email);

                break;

            case $email->getContent() instanceof Content\SimpleContent:
                $message[self::CONTENT] = $this->buildSimpleContent($email);
        }

        return $message;
    }

    /**
     * Attempt to create template data using the from, subject and reply to, which SparkPost considers to be
     * part of the templates substitutable content.
     *
     * @param Email $email
     *
     * @return array
     */
    protected function buildTemplateData(Email $email): array
    {
        /** @var Content\TemplatedContent $emailContent */
        $emailContent = $email->getContent();
        $templateData = $emailContent->getTemplateData();

        if ($email->getReplyTos()) {
            $replyTos = $email->getReplyTos();
            $first    = reset($replyTos);

            if (!array_key_exists('replyTo', $templateData)) {
                $templateData['replyTo'] = $first->toRfc2822();
            }
        }

        if (!array_key_exists('fromName', $templateData)) {
            $templateData['fromName'] = $email->getFrom()->getName();
        }

        if (!array_key_exists('fromAddress', $templateData)) {
            $templateData['fromAddress'] = $email->getFrom()->getEmail();
        }

        // Deprecated: fromEmail will be removed in later releases of the SparkPostCourier in favor of fromAddress
        if (!array_key_exists('fromEmail', $templateData)) {
            $templateData['fromEmail'] = explode('@', $email->getFrom()->getEmail())[0];
        }

        // Deprecated: fromDomain will be removed in later releases of the SparkPostCourier in favor of fromAddress
        if (!array_key_exists('fromDomain', $templateData)) {
            $templateData['fromDomain'] = explode('@', $email->getFrom()->getEmail())[1];
        }

        if (!array_key_exists('subject', $templateData)) {
            $templateData['subject'] = $email->getSubject();
        }

        // @TODO Remove this variable once SparkPost CC headers work properly for templates
        if (!array_key_exists('ccHeader', $templateData)) {
            if ($header = $this->buildCcHeader($email)) {
                $templateData['ccHeader'] = $header;
            }
        }

        return $templateData;
    }

    /**
     * @param Email $email
     *
     * @return array
     */
    protected function buildTemplateContent(Email $email): array
    {
        // SparkPost does not currently support templated emails with attachments, so it must be converted to a
        // SimpleContent message instead.
        if ($email->getAttachments()) {
            return $this->buildSimpleContent($this->templates->convertTemplatedEmail($email));
        }

        $content = [
            self::TEMPLATE_ID => $email->getContent()->getTemplateId(),
        ];

        if ($headers = $this->getContentHeaders($email)) {
            $content[self::HEADERS] = $headers;
        }

        return $content;
    }

    /**
     * @param Email $email
     *
     * @return array
     */
    protected function buildSimpleContent(Email $email): array
    {
        $replyTo = null;
        if (!empty($email->getReplyTos())) {
            // SparkPost only supports a single reply-to
            $replyTos = $email->getReplyTos();
            $first    = reset($replyTos);

            $replyTo = $first->toRfc2822();
        }

        /** @var Content\Contracts\SimpleContent $emailContent */
        $emailContent = $email->getContent();

        $content = [
            self::FROM          => [
                self::CONTACT_NAME  => $email->getFrom()->getName(),
                self::CONTACT_EMAIL => $email->getFrom()->getEmail(),
            ],
            self::SUBJECT       => $email->getSubject(),
            self::HTML          => $emailContent->getHtml() !== null ? $emailContent->getHtml()->getBody() : null,
            self::TEXT          => $emailContent->getText() !== null ? $emailContent->getText()->getBody() : null,
            self::ATTACHMENTS   => $this->buildAttachments($email),
            self::INLINE_IMAGES => $this->buildInlineAttachments($email),
            self::REPLY_TO      => $replyTo,
        ];

        if ($headers = $this->getContentHeaders($email)) {
            $content[self::HEADERS] = $headers;
        }

        return $content;
    }

    protected function getContentHeaders(Email $email): array
    {
        $headers = [];

        foreach ($email->getHeaders() as $header) {
            $headers[$header->getField()] = $header->getValue();
        }

        if ($ccHeader = $this->buildCcHeader($email)) {
            $headers[self::CC_HEADER] = $ccHeader;
        } else {
            // If this was set on a template in SparkPost, we will remove it, because there are no
            // CCs defined on the email itself.
            unset($headers[self::CC_HEADER]);
        }

        return $headers;
    }

    /**
     * @param Email $email
     *
     * @return array
     */
    private function buildAttachments(Email $email): array
    {
        $attachments = [];

        foreach ($email->getAttachments() as $attachment) {
            $attachments[] = [
                self::ATTACHMENT_NAME => $attachment->getName(),
                self::ATTACHMENT_TYPE => $attachment->getRfc2822ContentType(),
                self::ATTACHMENT_DATA => $attachment->getBase64Content(),
            ];
        }

        return $attachments;
    }

    /**
     * @param Email $email
     *
     * @return array
     */
    private function buildInlineAttachments(Email $email): array
    {
        $inlineAttachments = [];

        foreach ($email->getEmbedded() as $embedded) {
            $inlineAttachments[] = [
                self::ATTACHMENT_NAME => $embedded->getContentId(),
                self::ATTACHMENT_TYPE => $embedded->getRfc2822ContentType(),
                self::ATTACHMENT_DATA => $embedded->getBase64Content(),
            ];
        }

        return $inlineAttachments;
    }

    /**
     * @param Address $address
     * @param string  $headerTo
     *
     * @return array
     */
    private function createAddress(Address $address, string $headerTo): array
    {
        return [
            self::ADDRESS => [
                self::CONTACT_EMAIL => $address->getEmail(),
                self::HEADER_TO     => $headerTo,
            ],
        ];
    }

    /**
     * Build a string representing the header_to field of this email.
     *
     * @param Email $email
     *
     * @return string
     */
    private function buildHeaderTo(Email $email): string
    {
        return implode(',', array_map(function (Address $address) {
            return $address->toRfc2822();
        }, $email->getToRecipients()));
    }

    /**
     * Build a string representing the CC header for this email.
     *
     * @param Email $email
     *
     * @return string
     */
    private function buildCcHeader(Email $email): string
    {
        return implode(',', array_map(function (Address $address) {
            return $address->toRfc2822();
        }, $email->getCcRecipients()));
    }
}
