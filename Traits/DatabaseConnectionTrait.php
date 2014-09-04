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

trait DatabaseConnectionTrait
{
    protected function getDatabaseParameter()
    {
        // Fetch db connection data
        $siteaccess = $this->getContainer()->getParameter('data_transfer_bundle.siteaccess');

        $legacyParameter = sprintf('ezsettings.%s.database.params', $siteaccess);
        $repositoryParameter = sprintf('ezsettings.%s.repository', $siteaccess);
        if($this->getContainer()->hasParameter($legacyParameter)) {
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