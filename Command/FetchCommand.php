<?php
/**
 * This file is part of the data-transfer-bundle
 *
 * (c) Kuborgh GmbH
 *
 * For the full copyright and license information, please view the LICENSE.txt
 * file that was distributed with this source code.
 */

namespace Kuborgh\DataTransferBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command to fetch live data according to the configured parameters
 */
class FetchCommand extends AbstractCommand
{
    /**
     * Configure command
     */
    protected function configure()
    {
        $this
            ->setName('data-transfer:fetch')
            ->setDescription('Export all content types into the data folder.');
    }

    /**
     * Execute the command
     *
     * @param \Symfony\Component\Console\Input\InputInterface   $input  Input
     * @param \Symfony\Component\Console\Output\OutputInterface $output Output
     *
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        // Fetch and import live database
        $this->fetchDatabase();

        // fetch live data files
        $this->fetchFiles();
    }

    /**
     * Log in to the remote server and create a database dump.
     */
    protected function fetchDatabase()
    {
        $this->output->writeln('Fetching database');
        // Get and test database credentials
        // @todo
        $this->progressErr();

        // Login to the remote server and dump the database
        $remoteHost = $this->getParam('remote.host');
        $remoteDir = $this->getParam('remote.dir');
        $cmd = sprintf('ssh -i ~/id_rsa %s %s/console data-transfer:export', $remoteHost, $remoteDir);
        $this->output->writeln($cmd);
        // @todo
        $this->progressErr();

        // Create dump
        // @todo
        $this->progressErr();

        // Import dump
        // @todo
        $this->progressErr();

        $this->progressDone();
    }

    /**
     * Fetch files from remote server
     */
    protected function fetchFiles()
    {
        $this->output->writeln('Fetching files');
        // Use rsync to fetch files
        // @todo
        $this->progressErr();

        $this->progressDone();
    }

    /**
     * Fetch a parameter from config
     *
     * @param String $param Name of the parameter (without the ugly prefixes)
     *
     * @return mixed
     */
    protected function getParam($param)
    {
        return $this->getContainer()->getParameter('data_transfer_bundle.' . $param);
    }
} 