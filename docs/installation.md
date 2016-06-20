# Requirements

* PHP >= 5.6.0,
* [Guzzle 6](https://github.com/guzzle/guzzle) library and the [Guzzle OAuth1 Subscriber](https://github.com/guzzle/oauth-subscriber),
* (optional) [PHPUnit](https://phpunit.de/) and [php-cs-fixer](http://cs.sensiolabs.org/) to run tests.

# Installation

The recommended method of installing phpZenfolio is using [Composer](http://getcomposer.org). If you have Composer installed, you can install phpZenfolio and all its dependencies from within your project directory:

```bash
$ composer require lildude/phpzenfolio
```

Alternatively, you can add the following to your project's `composer.json`:

```json
{
    "require": {
        "lildude/phpzenfolio": "^2.0"
    }
}
```

.. and then run `composer update` from within your project directory.

If you don't have Composer installed, you can download it using:

```bash
$ curl -s http://getcomposer.org/installer | php
```

**Note: phpZenfolio 2.0.0 and later is not backwardly compatible with earlier releases.**
