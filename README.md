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
Import configuration into main config. Add
```
imports:
    - {resource: "@DataTransferBundle/Resources/config/parameters.yml"}
```
into your config.yml

### 3. Register Bundle
Add the bundle to your AppKernel.php
```
$bundles[] = new DataTransferBundle();
```

### 4. Configuration
* Adapt configuration (parameters.yml + parameters.yml.dist) to your project's needs (Server, Path, siteaccess, ...)

## Configuration ##

See Resources/config/parameters.yml for details

## Usage ##

To transfer database+files from the remote server to your develop environment simply call
```
php app/console data-transfer:fetch
```

To only transfer the database, use
```
php app/console data-transfer:fetch --db-only
```

To only transfer files, use
```
php app/console data-transfer:fetch --files-only
```
