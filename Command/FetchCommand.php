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
use Symfony\Component\Process\Process;

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
            ->setDescription('Fetch remote database and files from configured system.');
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
        try {
            $this->fetchDatabase();
        } catch (\Exception $exc) {
            $this->progressErr($exc->getMessage());
        }
        $this->progressDone();

        // fetch live data files
        $this->fetchFiles();
    }

    /**
     * Log in to the remote server and dump the database.
     */
    protected function fetchDatabase()
    {
        $this->output->writeln('Fetching database');
        // Get and test database credentials
        // @todo

        // Prepare remote command
        $remoteHost = $this->getParam('remote.host');
        $remoteUser = $this->getParam('remote.user');
        $remoteDir = $this->getParam('remote.dir');
        $remoteEnv = $this->getParam('remote.env');
        $options = $this->getParam('ssh.options');
        $exportCmd = sprintf(
            'ssh %s %s@%s php %s/console %s data-transfer:export 2>&1',
            implode(' ', $options),
            $remoteUser,
            $remoteHost,
            $remoteDir,
            $remoteEnv ? '--env=' . $remoteEnv : ''
        );
        $this->progress();

        // Execute command
        $process = new Process($exportCmd);
        $process->run();
        $this->progress();

        // Check for error
        if (!$process->isSuccessful()) {
            throw new \Exception(sprintf('Cannot connect to remote host: %s', $process->getOutput()));
        }

        // Check if we have a valid dump in our output
        $sqlDump = $process->getOutput();

        // first line must start with '-- MySQL dump' and end with '-- Dump completed'
        if (!preg_match('/^\-\- MySQL dump/', $sqlDump) || !preg_match(
                '/\-\- Dump completed on \d*\-\d*\-\d* \d+\:\d+\:\d+[\r\n\s\t]*$/',
                $sqlDump
            )
        ) {
            throw new \Exception(sprintf('Error on remote host: %s', $process->getOutput()));
        }

        // Otherwise we now have sql dump in our output
        $this->progressOk();

        // Save to temporary file
        $tmpFile = $this->getContainer()->getParameter('kernel.cache_dir') . '/data-transfer.sql';
        file_put_contents($tmpFile, $sqlDump);
        $this->progress();

        // Import Dump

        // Fetch db connection data
        $siteaccess = $this->getContainer()->getParameter('data_transfer_bundle.siteaccess');
        $dbParams = $this->getContainer()->getParameter(sprintf('ezsettings.%s.database.params', $siteaccess));
        $dbName = $dbParams['database'];
        $dbUser = $dbParams['user'];
        $dbPass = $dbParams['password'];
        $dbHost = $dbParams['host'];

//        die(escapeshellarg($sqlDump));

        $importCmd = sprintf(
            'mysql %s --user=%s --password=%s --host=%s < %s 2>&1',
            escapeshellarg($dbName),
            escapeshellarg($dbUser),
            escapeshellarg($dbPass),
            escapeshellarg($dbHost),
            escapeshellarg($tmpFile)
        );

        $process = new Process($importCmd);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new \Exception(sprintf('Error importing database : %s', $process->getOutput()));
        }
        $this->progressOk();
    }

    /**
     * Fetch files from remote server
     */
    protected function fetchFiles()
    {
        $this->output->writeln('Fetching files');
        // Use rsync to fetch files
        // @todo
        $this->progressErr('Not implemented yet');

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