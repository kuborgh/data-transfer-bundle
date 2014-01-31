data-transfer-bundle
====================

This is a bundle to provide easy transfer of server data to the client.

## INSTALLATION ##

1. Add the following to the composer.json inside your project. The package is yet not registered at packagist and can thus not be installed with one command. Please add the following to your composer.json first
```
"repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/kuborgh/data-transfer-bundle"
        }
    ],
```

2. Install the dependency via composer
```
composer require kuborgh/data-transfer-bundle "*"
```

3. Import configuration into main config. Add
```
imports:
    - {resource: "@DataTransferBundle/Resources/config/parameters.yml"}
```
into your config.yml

4. Adapt configuration to your project's needs (Server, Path, Siteaccess, ...)

5. Adapt your development configuration to your need (ssh key)

## Configuration ##

See Resources/config/parameters.yml for details

## Usage ##

To transfer fiels from the remote server to your develop environment simply call
```
php app/console data-transfer:fetch
```
