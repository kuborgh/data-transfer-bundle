data-transfer-bundle
====================

Bundle to provide easy transfer of server data to the client.

## INSTALLATION ##

1. add to composer.json of your projekt. The package is yet not registered at packagist and can thus not be installed with one command. Please add the following to your composer.json first
```
"repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/kuborgh/data-transfer-bundle
"
        }
    ],
```
Then install the dependency via
```
composer require kuborgh/data-transfer-bundle *@dev
```

2. Import configuration into main config. Add
```
imports:
    - {resource: "@DataTransferBundle/Resources/config/parameters.yml"}
```
into your config.yml

## Configuration ##

See Resources/config/parameters.yml for details

## Usage ##

TBD
```
php app/console data-transfer:fetch
```