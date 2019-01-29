<?php

declare(strict_types=1);

namespace Courier\SparkPost;

use Courier\Exceptions\TransmissionException;
use Courier\Exceptions\ValidationException;
use PhpEmail\Address;
use PhpEmail\Content;
use PhpEmail\Email;
use PhpEmail\Header;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SparkPost\SparkPost;
use SparkPost\SparkPostException;

/**
 * A class to retrieve SparkPost templates and convert them into SimpleContent Email classes.
 *
 * SparkPost does not currently support sending attachments with templated emails. For this reason,
 * this class will instead get the template from SparkPost and create a new SimpleContent Email object with enough
 * properties set to allow the courier to build the email content.
 */
class SparkPostTemplates
{
    /**
     * @var SparkPost
     */
    private $sparkPost;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(SparkPost $sparkPost, LoggerInterface $logger = null)
    {
        $this->sparkPost = $sparkPost;
        $this->logger    = $logger ?: new NullLogger();
    }

    /**
     * Convert a templated email into simple content email using the content of the template stored on SparkPost.
     *
     * @param Email $email
     *
     * @return Email
     */
    public function convertTemplatedEmail(Email $email): Email
    {
        $template    = $this->getTemplate($email);
        $inlineEmail = clone $email;

        $inlineEmail
            ->setSubject($template[SparkPostCourier::SUBJECT])
            ->setContent($this->getInlineContent($template));

        // If the from contains a templated from, it should be actively replaced now to avoid validation errors.
        if (strpos($template[SparkPostCourier::FROM][SparkPostCourier::CONTACT_EMAIL], '{{') !== false) {
            $inlineEmail->setFrom($email->getFrom());
        } else {
            $inlineEmail->setFrom(
                new Address(
                    $template[SparkPostCourier::FROM][SparkPostCourier::CONTACT_EMAIL],
                    $template[SparkPostCourier::FROM][SparkPostCourier::CONTACT_NAME]
                )
            );
        }

        // If the form contains a templated replyTo, it should be actively replaced now to avoid validation errors.
        if (array_key_exists(SparkPostCourier::REPLY_TO, $template)) {
            if (strpos($template[SparkPostCourier::REPLY_TO], '{{') !== false) {
                if (empty($email->getReplyTos())) {
                    throw new ValidationException('Reply to is templated but no value was given');
                }

                $inlineEmail->setReplyTos($email->getReplyTos()[0]);
            } else {
                $inlineEmail->setReplyTos(Address::fromString($template[SparkPostCourier::REPLY_TO]));
            }
        }

        // Include headers defined on the template
        if (array_key_exists(SparkPostCourier::HEADERS, $template)) {
            foreach ($template[SparkPostCourier::HEADERS] as $key => $value) {
                $inlineEmail->addHeaders(new Header($key, $value));
            }
        }

        if ($email->getHeaders()) {
            $inlineEmail->addHeaders(...$email->getHeaders());
        }

        return $inlineEmail;
    }

    /**
     * Create the SimpleContent based on the SparkPost template data.
     *
     * @param array $template
     *
     * @return Content\SimpleContent
     */
    protected function getInlineContent(array $template): Content\SimpleContent
    {
        $htmlContent = null;
        if (array_key_exists(SparkPostCourier::HTML, $template)) {
            $htmlContent = new Content\SimpleContent\Message($template[SparkPostCourier::HTML]);
        }

        $textContent = null;
        if (array_key_exists(SparkPostCourier::TEXT, $template)) {
            $textContent = new Content\SimpleContent\Message($template[SparkPostCourier::TEXT]);
        }

        return new Content\SimpleContent($htmlContent, $textContent);
    }

    /**
     * Get the template content from SparkPost.
     *
     * @param Email $email
     *
     * @throws TransmissionException
     *
     * @return array
     */
    protected function getTemplate(Email $email): array
    {
        try {
            $response = $this->sparkPost->syncRequest('GET', "templates/{$email->getContent()->getTemplateId()}");

            return $response->getBody()['results'][SparkPostCourier::CONTENT];
        } catch (SparkPostException $e) {
            $this->logger->error(
                'Received status {code} from SparkPost while retrieving template with body: {body}',
                [
                    'code' => $e->getCode(),
                    'body' => $e->getBody(),
                ]
            );

            throw new TransmissionException($e->getCode(), $e);
        }
    }
}
