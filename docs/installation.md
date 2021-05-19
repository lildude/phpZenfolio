# Requirements

* PHP >= 5.6.0,
* [Guzzle 6](https://github.com/guzzle/guzzle) library,
* (optional) [PHPUnit](https://phpunit.de/), [php-coveralls](https://github.com/php-coveralls/php-coveralls) and [php-cs-fixer](https://cs.sensiolabs.org/) to run tests.

# Installation

The recommended method of installing phpZenfolio is using [Composer](https://getcomposer.org). If you have Composer installed, you can install phpZenfolio and all its dependencies from within your project directory:

    $ composer require lildude/phpzenfolio


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

    $ curl -s https://getcomposer.org/installer | php
