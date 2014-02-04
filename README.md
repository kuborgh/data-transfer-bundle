data-transfer-bundle
====================

This is a bundle to provide easy transfer of server data to the client.

## INSTALLATION ##

### 1. Composer Repository
Add the following to the composer.json inside your project. The package is yet not registered at packagist and can thus not be installed with one command. Please add the following to your composer.json first
```
"repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/kuborgh/data-transfer-bundle"
        }
    ],
```

### 2. Composer Dependency
Install the dependency via composer
```
composer require kuborgh/data-transfer-bundle "*@dev"
```

### 3. Configuration
Import configuration into main config. Add
```
imports:
    - {resource: "@DataTransferBundle/Resources/config/parameters.yml"}
```
into your config.yml

### 4. Register Bundle
Add the bundle to your Kernel
```
$bundles[] = new DataTransferBundle();
```

### 5. Configuration
* Adapt configuration (parameters.yml + parameters.yml.dist) to your project's needs (Server, Path, Siteaccess, ...)
* Adapt your development configuration (config_<dev>.yml) to your need (SSH Key)

## Configuration ##

See Resources/config/parameters.yml for details

## Usage ##

To transfer fiels from the remote server to your develop environment simply call
```
php app/console data-transfer:fetch
```
