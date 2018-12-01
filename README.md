# Sparkpost Courier

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Total Downloads][ico-downloads]][link-downloads]
[![Software License][ico-license]](LICENSE)
[![Build Status][ico-travisci]][link-travisci]
[![Coverage Status][ico-codecov]][link-codecov]
[![Style Status][ico-styleci]][link-styleci]
[![Scrutinizer Code Quality][ico-scrutinizer]][link-scrutinizer]

A courier implementation for Sparkpost.

See [documentation](https://quartzy.github.io/courier/couriers/sparkpost/) for full details.

## Install

### Via Composer

```bash
composer require quartzy/courier-sparkpost
```

You will also need to install a php-http implementation library
[as defined in the Sparkpost docs](https://github.com/SparkPost/php-sparkpost#installation).

## Usage

```php
<?php

use Courier\SparkPostCourier;
use GuzzleHttp\Client;
use Http\Adapter\Guzzle6\Client as GuzzleAdapter;
use PhpEmail\Content\TemplatedContent;
use PhpEmail\EmailBuilder;
use SparkPost\SparkPost;

new Client();

$courier = new SparkPostCourier(
    new SparkPost(new GuzzleAdapter(new Client()), ['key'=>'YOUR_API_KEY'])
);

$email = EmailBuilder::email()
    ->from('test@mybiz.com')
    ->to('loyal.customer@email.com')
    ->replyTo('test@mybiz.com', 'Your Sales Rep')
    ->withSubject('Welcome!')
    ->withContent(new TemplatedContent('my_email', ['testKey' => 'value']))
    ->build();

$courier->deliver($email);
```

For details on building the email objects, see [Php Email](https://github.com/quartzy/php-email).


## Change log

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) and [CONDUCT](CONDUCT.md) for details.

## Security

If you discover any security related issues, please email [opensource@quartzy.com](mailto:opensource@quartzy.com) instead of using the issue tracker.

## Credits

- [Chris Muthig](https://github.com/camuthig)
- [All Contributors][link-contributors]


## License

The Apache License, v2.0. Please see [License File](LICENSE) for more information.

[ico-version]: https://img.shields.io/packagist/v/quartzy/courier-sparkpost.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/license-Apache%202.0-brightgreen.svg?style=flat-square
[ico-travisci]: https://img.shields.io/travis/quartzy/courier-sparkpost.svg?style=flat-square
[ico-codecov]: https://img.shields.io/scrutinizer/coverage/g/quartzy/courier-sparkpost.svg?style=flat-square
[ico-styleci]: https://styleci.io/repos/98693280/shield
[ico-scrutinizer]: https://img.shields.io/scrutinizer/g/quartzy/courier-sparkpost.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/quartzy/courier-sparkpost.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/quartzy/courier-sparkpost
[link-travisci]: https://travis-ci.org/quartzy/courier-sparkpost
[link-codecov]: https://scrutinizer-ci.com/g/quartzy/courier-sparkpost
[link-styleci]: https://styleci.io/repos/projectid
[link-scrutinizer]: https://scrutinizer-ci.com/g/quartzy/courier-sparkpost
[link-downloads]: https://packagist.org/packages/quartzy/courier-sparkpost
[link-contributors]: ../../contributors
