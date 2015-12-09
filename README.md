data-transfer-bundle
====================

This is a bundle to provide easy transfer of server data (database + files) to the client. It can be used in plain symfony projects or for ezpublish >= 5.x

## INSTALLATION ##

### 1. Composer
Install the dependency via composer.
```
composer require kuborgh/data-transfer-bundle
```

### 2. Configuration
Import configuration into main config (app/config/config.yml). Add the following line
```
imports:
    - {resource: @DataTransferBundle/Resources/config/parameters.yml}
```
into your config.yml

### 3. Register Bundle
Add the bundle in app/AppKernel.php
```
$bundles[] = new Kuborgh\DataTransferBundle\DataTransferBundle();
```

### 4. Configuration
* Adapt configuration (parameters.yml + parameters.yml.dist) to your project's needs (Server, Path, siteaccess, ...)

## Configuration ##

See Resources/config/parameters.yml for details

## Usage ##

To transfer database+files from the remote server to your develop environment simply call.
```
php app/console data-transfer:fetch
```

NOTE: The bundle must be already deployed on the remote side in order to work.

To limit the transfer to database or files only, use
```
php app/console data-transfer:fetch --db-only
```
or 
```
php app/console data-transfer:fetch --files-only
```
