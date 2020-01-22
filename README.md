# laravel-db-upgrade

[![Latest Stable Version](https://poser.pugx.org/eXolnet/laravel-db-upgrade/v/stable?format=flat-square)](https://packagist.org/packages/eXolnet/laravel-db-upgrade)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)
[![Build Status](https://img.shields.io/travis/eXolnet/laravel-db-upgrade/master.svg?style=flat-square)](https://travis-ci.org/eXolnet/laravel-db-upgrade)
[![Total Downloads](https://img.shields.io/packagist/dt/eXolnet/laravel-db-upgrade.svg?style=flat-square)](https://packagist.org/packages/eXolnet/laravel-db-upgrade)

Artisan command to migrate an existing production database structure to use Laravel migrations

## Installation

Require this package with composer:

``` bash
composer require exolnet/laravel-db-upgrade
```

The package will automatically register its service provider.

Publish the config file to `config/db-upgrade.php` using:

``` bash
php artisan vendor:publish --provider="Exolnet\DbUpgrade\DbUpgradeServiceProvider"
```

Review and update the default configuration according to your use case (see Usage section for more information).

## Usage

Explain how to use your package.

## Testing

To run the phpUnit tests, please use:

``` bash
composer test
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) and [CODE OF CONDUCT](CODE_OF_CONDUCT.md) for details.

## Security

If you discover any security related issues, please email security@exolnet.com instead of using the issue tracker.

## Credits

- [Alexandre D'Eschambeault](https://github.com/xel1045)
- [All Contributors](../../contributors)

## License

This code is licensed under the [MIT license](http://choosealicense.com/licenses/mit/). 
Please see the [license file](LICENSE) for more information.
