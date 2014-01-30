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

## Configuration ##

TBD

## Usage ##

TBD
```
php app/console data-transfer:fetch
```