<?php
/**
 * This file is part of the data-transfer-bundle
 *
 * (c) Kuborgh GmbH
 *
 * For the full copyright and license information, please view the LICENSE.txt
 * file that was distributed with this source code.
 */

namespace Kuborgh\DataTransferBundle\Traits;

use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Database connection trait.
 * Provides helper functions to get the database connection on the current system.
 */
trait DatabaseConnectionTrait
{
    /**
     * Get database connection.
     *
     * @throws \Exception
     *
     * @return string[]
     */
    protected function getDatabaseParameter()
    {
        if ($this->getContainer()->getParameter('data_transfer_bundle.siteaccess')) {
            $parameters = $this->getEzPublishDatabase();
        } else {
            $parameters = $this->getSymfonyDatabase();
        }

        # Additional database import arguments
        $importArgs = $this->getContainer()->getParameter('data_transfer_bundle.database.import_arguments');
        if (is_array($importArgs)) {
            $parameters['databaseImportArguments'] = $importArgs;
        } elseif (is_string($importArgs)) {
            $parameters['databaseImportArguments'] = array($importArgs);
        }

        # Additional database export arguments
        $exportArgs = $this->getContainer()->getParameter('data_transfer_bundle.database.export_arguments');
        if (is_array($exportArgs)) {
            $parameters['databaseExportArguments'] = $exportArgs;
        } elseif (is_string($exportArgs)) {
            $parameters['databaseExportArguments'] = array($exportArgs);
        }

        return $parameters;
    }

    /**
     * Use default parameters ( should be provided in parameters.yml )
     *
     * @return string[]
     */
    private function getSymfonyDatabase()
    {
        return array(
            'dbName' => $this->getContainer()->getParameter('database_name'),
            'dbUser' => $this->getContainer()->getParameter('database_user'),
            'dbPass' => $this->getContainer()->getParameter('database_password'),
            'dbHost' => $this->getContainer()->getParameter('database_host'),
        );
    }

    /**
     * Get database from eZ publish.
     * Works with new doctrine connection and legacy configuration.
     *
     * @throws \Exception
     *
     * @return string[]
     */
    private function getEzPublishDatabase()
    {
        // Fetch db connection data
        $siteaccess = $this->getContainer()->getParameter('data_transfer_bundle.siteaccess');

        $legacyParameter = sprintf('ezsettings.%s.database.params', $siteaccess);
        $repositoryParameter = sprintf('ezsettings.%s.repository', $siteaccess);
        if ($this->getContainer()->hasParameter($legacyParameter)) {
            $dbParams = $this->getContainer()->getParameter($legacyParameter);

            return array(
                'dbName' => $dbParams['database'],
                'dbUser' => $dbParams['user'],
                'dbPass' => $dbParams['password'],
                'dbHost' => $dbParams['host'],
            );
        } elseif ($this->getContainer()->hasParameter($repositoryParameter)) {
            $repository = $this->getContainer()->getParameter($repositoryParameter);
            $repositories = $this->getContainer()->getParameter('ezpublish.repositories');
            $connection = $repositories[$repository]['connection'];
            /** @var $dbalConnection Connection */
            $dbalConnection = $this->getContainer()->get(sprintf('doctrine.dbal.%s_connection', $connection));

            return array(
                'dbName' => $dbalConnection->getDatabase(),
                'dbUser' => $dbalConnection->getUsername(),
                'dbPass' => $dbalConnection->getPassword(),
                'dbHost' => $dbalConnection->getHost(),
            );
        } else {
            $message = "Unable to find database settings from siteaccess. You need to define either %s or %s";
            throw new \Exception(sprintf($message, $legacyParameter, $repositoryParameter));
        }
    }

    /**
     * @return ContainerInterface
     */
    abstract function getContainer();
}
