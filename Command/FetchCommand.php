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
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 * Command to fetch live data according to the configured parameters
 */
class FetchCommand extends AbstractCommand
{
    /**
     * Regex to check if the remote dump is ok
     * must start with '-- MySQL dump'
     */
    const VALID_DUMP_REGEX_1 = '/^\-\- MySQL dump/';

    /**
     * Regex to check if the remote dump is ok
     * must end with '-- Dump completed'
     */
    const VALID_DUMP_REGEX_2 = '/\-\- Dump completed on \d*\-\d*\-\d* \d+\:\d+\:\d+[\r\n\s\t]*$/';

    /**
     * Configure command
     */
    protected function configure()
    {
        $this
            ->setName('data-transfer:fetch')
            ->setDescription('Fetch remote database and files from configured system.')
            ->addOption('db-only', 'db-only', InputOption::VALUE_NONE, 'Only transfer the database, not the files.')
            ->addOption(
                'files-only',
                'files-only',
                InputOption::VALUE_NONE,
                'Only transfer the files, not the database.'
            );
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
        if (!$input->getOption('files-only')) {
            try {
                $this->fetchDatabase();
            } catch (\Exception $exc) {
                $this->progressErr($exc->getMessage());
            }
            $this->progressDone();
        }

        // fetch live data files
        if (!$input->getOption('db-only')) {
            try {
                $this->fetchFiles();
            } catch (\Exception $exc) {
                $this->progressErr($exc->getMessage());
            }
            $this->progressDone();
        }

    }

    /**
     * Log in to the remote server and dump the database.
     */
    protected function fetchDatabase()
    {
        $this->output->writeln('Fetching database');

        // Prepare remote command
        $remoteHost = $this->getParam('remote.host');
        $remoteUser = $this->getParam('remote.user');
        $remoteDir = $this->getParam('remote.dir');
        $remoteEnv = $this->getParam('remote.env');
        $consoleCmd = $this->getParam('console_script');
        $options = $this->getParam('ssh.options');
        $exportCmd = sprintf(
            'ssh %s %s@%s "cd %s ; %s %s data-transfer:export 2>&1"',
            implode(' ', $options),
            $remoteUser,
            $remoteHost,
            $remoteDir,
            $consoleCmd,
            $remoteEnv ? '--env=' . $remoteEnv : ''
        );
        $this->progress();

        // Execute command
        $process = new Process($exportCmd);
        $process->run(
            function () {
                $this->progress();
            }
        );
        $this->progress();
        // Check for error
        if (!$process->isSuccessful()) {
            throw new \Exception(sprintf('Cannot connect to remote host: %s', $process->getOutput()));
        }

        // Check if we have a valid dump in our output
        // first line must start with '-- MySQL dump' and end with '-- Dump completed'
        $sqlDump = $process->getOutput();
        if (!preg_match(self::VALID_DUMP_REGEX_1, $sqlDump) || !preg_match(self::VALID_DUMP_REGEX_2, $sqlDump)) {
            throw new \Exception(sprintf('Error on remote host: %s', $process->getOutput()));
        }
        $this->progressOk();

        // Save dump to temporary file
        $tmpFile = $this->getContainer()->getParameter('kernel.cache_dir') . '/data-transfer.sql';
        file_put_contents($tmpFile, $sqlDump);
        $this->progress();

        // Fetch db connection data
        $siteaccess = $this->getContainer()->getParameter('data_transfer_bundle.siteaccess');
        $dbParams = $this->getContainer()->getParameter(sprintf('ezsettings.%s.database.params', $siteaccess));
        $dbName = $dbParams['database'];
        $dbUser = $dbParams['user'];
        $dbPass = $dbParams['password'];
        $dbHost = $dbParams['host'];

        // Import Dump
        $importCmd = sprintf(
            'mysql %s --user=%s --password=%s --host=%s < %s 2>&1',
            escapeshellarg($dbName),
            escapeshellarg($dbUser),
            escapeshellarg($dbPass),
            escapeshellarg($dbHost),
            escapeshellarg($tmpFile)
        );

        $process = new Process($importCmd);
        $process->run(
            function () {
                $this->progress();
            }
        );
        if (!$process->isSuccessful()) {
            throw new \Exception(sprintf('Error importing database : %s', $process->getOutput()));
        }
        $this->progressOk();

        // Remove temp dump file
        if (file_exists($tmpFile)) {
            unlink($tmpFile);
        }
        $this->progress();
    }

    /**
     * Fetch files from remote server
     */
    protected function fetchFiles()
    {
        $this->output->writeln('Fetching files');

        // Fetch folders to rsync
        $folders = $this->getParam('folders');
        $rsyncOptions = $this->getParam('rsync.options');
        $sshOptions = $this->getParam('ssh.options');

        // Fetch params
        $remoteHost = $this->getParam('remote.host');
        $remoteUser = $this->getParam('remote.user');
        $remoteDir = $this->getParam('remote.dir');

        // Loop over the folders, to be transfered
        foreach ($folders as $folder) {
            // Prepare command
            $cmd = sprintf(
                'rsync %s -e "ssh %s" %s@%s:%s/%s %s/ 2>&1',
                implode(' ', $rsyncOptions),
                implode(' ', $sshOptions),
                $remoteUser,
                $remoteHost,
                $remoteDir,
                $folder,
                dirname($folder)
            );

            // Run (with callback to update those fancy dots
            $process = new Process($cmd);
            $process->run(
                function () {
                    $this->progress();
                }
            );
            if (!$process->isSuccessful()) {
                throw new \Exception(sprintf('Error fetching files: %s', $process->getOutput()));
            }

            $this->progressOk();
        }
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