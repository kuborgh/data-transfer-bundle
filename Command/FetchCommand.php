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

use Kuborgh\DataTransferBundle\Traits\DatabaseConnectionTrait;
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
    const VALID_DUMP_REGEX_2 = '/\-\- Dump completed on\s+\d*\-\d*\-\d*\s+\d+\:\d+\:\d+[\r\n\s\t]*$/';

    /**
     * Trait for determine the local database connection based on a given siteaccess
     */
    use DatabaseConnectionTrait;

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
        $useFile = $this->getParam('db_via_file');

        // Check for ssh proxy
        $sshProxyString = $this->getSshProxyOption();
        if ($sshProxyString) {
            $options[] = $sshProxyString;
        }

        $exportCmd = sprintf(
            'ssh %s %s@%s "cd %s ; %s %s data-transfer:export %s 2>&1"',
            implode(' ', $options),
            $remoteUser,
            $remoteHost,
            $remoteDir,
            $consoleCmd,
            $remoteEnv ? '--env=' . $remoteEnv : '',
            $useFile ? '--file' : ''
        );
        $this->progress();

        $cacheFolder = $this->getContainer()->getParameter('kernel.cache_dir');

        // Create file handle to save the stream to
        if (!$useFile) {
            $tmpFile = $cacheFolder . '/data-transfer.sql';
            $tmpFileHandle = fopen($tmpFile, 'w');

            // Execute command
            $process = new Process($exportCmd);
            $process->setTimeout(null);
            $bytes = 0;
            // Update status for each megabyte
            $process->run(
                function ($type, $buffer) use (&$bytes, $tmpFileHandle, $process) {
                    if ($type == Process::OUT) {
                        // Update progress
                        $bytes += strlen($buffer);
                        if ($bytes / 1024 / 1024 >= 1) {
                            $this->progress();
                            $bytes = 0;
                        }
                        $process->getOutput();

                        // write to file
                        fwrite($tmpFileHandle, $buffer);
                    }
                }
            );


            // Check for error
            if (!$process->isSuccessful()) {
                throw new \Exception(sprintf(
                    'Cannot connect to remote host: %s %s',
                    $process->getOutput(),
                    $process->getErrorOutput()
                ));
            }
            $this->progressOk();

            // Check if we have a valid dump in our output
            // first line must start with '-- MySQL dump' and end with '-- Dump completed'
            rewind($tmpFileHandle);
            $start = fgets($tmpFileHandle, 4096);
            fseek($tmpFileHandle, 4096, SEEK_END);
            $end = fgets($tmpFileHandle, 4096);
            if (!preg_match(self::VALID_DUMP_REGEX_1, $start) || !preg_match(self::VALID_DUMP_REGEX_2, $end)) {
                throw new \Exception(sprintf('Error on remote host: %s', $process->getOutput()));
            }
            $this->progressOk();

            // Close file handle
            fclose($tmpFileHandle);
            $this->progressDone();
        } else {
            // Execute command
            $process = new Process($exportCmd);
            $process->setTimeout(null);
            $process->run();

            // Check for error
            if (!$process->isSuccessful()) {
                throw new \Exception(sprintf(
                    'Cannot connect to remote host: %s %s',
                    $process->getOutput(),
                    $process->getErrorOutput()
                ));
            }
            $this->progressOk();

            // Extract information
            $json = $process->getOutput();
            $data = json_decode($json, true);

            // Fetch file via rsync
            $tmpFile = $cacheFolder . '/' . $data['basename'];
            $this->rSync($data['filename'], $tmpFile);
            $this->progressOk();

            // Remove remote dump
            $this->execRemoteCommand(sprintf('rm %s', $data['filename']));
            $this->progressOk();

            $this->progressDone();
        }

        // Import database
        $this->output->writeln('Importing database');

        // Fetch db connection data
        $dbParams = $this->getDatabaseParameter();

        // Import Dump
        $databaseImportArguments = $dbParams['databaseImportArguments'];
        if ($databaseImportArguments !== '') {
            $databaseImportArguments = implode(' ', array_map('escapeshellarg', explode(' ', $databaseImportArguments)));
        }
        $importCmd = sprintf(
            'mysql %s --user=%s --password=%s --host=%s %s < %s 2>&1',
            escapeshellarg($dbParams['dbName']),
            escapeshellarg($dbParams['dbUser']),
            escapeshellarg($dbParams['dbPass']),
            escapeshellarg($dbParams['dbHost']),
            $databaseImportArguments,
            escapeshellarg($tmpFile)
        );
        $this->progress();

        $process = new Process($importCmd);
        $process->setTimeout(null);
        // Update status for each megabyte
        $process->run();
        $this->progress();

        if (!$process->isSuccessful()) {
            throw new \Exception(sprintf(
                'Error importing database: %s %s',
                $process->getOutput(),
                $process->getErrorOutput()
            ));
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
        // Fetch folders to rsync
        $folders = $this->getParam('folders');
        $rsyncOptions = $this->getParam('rsync.options');
        $sshOptions = $this->getParam('ssh.options');

        // Check for ssh proxy
        $sshProxyString = $this->getSshProxyOption();
        if ($sshProxyString) {
            $sshOptions[] = $sshProxyString;
        }

        // Fetch params
        $remoteHost = $this->getParam('remote.host');
        $remoteUser = $this->getParam('remote.user');
        $remoteDir = $this->getParam('remote.dir');

        // Loop over the folders, to be transfered
        foreach ($folders as $src => $dst) {
            // If src = numeric, then indiced array was taken. detect folder automatically
            if (is_numeric($src)) {
                $src = $dst;
                $dst = dirname($src);
            }

            // Prepare command
            $cmd = sprintf(
                'rsync -P %s -e \'ssh %s\' %s@%s:%s/%s %s/ 2>&1',
                implode(' ', $rsyncOptions),
                implode(' ', $sshOptions),
                $remoteUser,
                $remoteHost,
                $remoteDir,
                $src,
                $dst
            );

            // Run (with callback to update those fancy dots
            $process = new Process($cmd);
            $process->setTimeout(null);

            $lastCnt = 0;
            $counting = true;
            $this->output->writeln('Counting files');
            $process->run(
                function ($type, $buffer) use (&$lastCnt, &$counting) {
                    if ($type == Process::OUT) {
                        if (preg_match('/(\d+)\sfiles.../', $buffer, $matches)) {
                            // Still counting
                            $diff = ($matches[1] - $lastCnt) / 100;
                            for ($i = 0; $i < $diff; $i++) {
                                $this->progress();
                            }
                            $lastCnt = $matches[1];

                        } elseif (preg_match('/xfer#(\d+), to\-check=(\d+)\/(\d+)/', $buffer, $matches)) {
                            // Finished counting, now downloading
                            if ($counting) {
                                $counting = false;
                                $this->progressDone();
                                $this->output->writeln(sprintf('Found %d files/folders', $lastCnt));
                                $this->output->writeln('');
                                $this->output->writeln('Syncing files');
                                $lastCnt = 0;
                            }

                            $diff = floor(($matches[1] - $lastCnt) / 100);
                            for ($i = 0; $i < $diff; $i++) {
                                $this->progress();
                            }
                            if ($diff) {
                                $lastCnt += $diff * 100;
                            }
                        }
                    }
                }
            );
            if ($counting) {
                $this->output->writeln('Files already up-to-date');
            }

            if (!$process->isSuccessful()) {
                throw new \Exception(sprintf(
                    'Error fetching files: %s %s',
                    $process->getOutput(),
                    $process->getErrorOutput()
                ));
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

    /**
     * Find ssh proxy options and return as ssh option string
     *
     * @return String
     */
    protected function getSshProxyOption()
    {
        // Check for ssh proxy
        $sshProxyHost = $this->getParam('ssh.proxy.host');
        $sshProxyUser = $this->getParam('ssh.proxy.user');
        $sshProxyOptions = $this->getParam('ssh.proxy.options');

        // No host or user -> no proxy
        if (!$sshProxyHost || !$sshProxyUser) {
            return '';
        }

        // Build option string
        $opt = sprintf(
            '-o ProxyCommand="ssh -W %%h:%%p %s %s@%s"',
            implode(' ', $sshProxyOptions),
            $sshProxyUser,
            $sshProxyHost
        );

        return $opt;
    }

    /**
     * Call rsync with all needed params
     *
     * @param String   $src      Source
     * @param String   $dst      Destination
     * @param callable $callback Optional callback
     */
    protected function rSync($src, $dst, $callback = null)
    {
        $rsyncOptions = $this->getParam('rsync.options');
        $sshOptions = $this->getParam('ssh.options');
        $remoteHost = $this->getParam('remote.host');
        $remoteUser = $this->getParam('remote.user');

        // Check for ssh proxy
        $sshProxyString = $this->getSshProxyOption();
        if ($sshProxyString) {
            $sshOptions[] = $sshProxyString;
        }

        $cmd = sprintf(
            'rsync -P %s -e \'ssh %s\' %s@%s:%s %s 2>&1',
            implode(' ', $rsyncOptions),
            implode(' ', $sshOptions),
            $remoteUser,
            $remoteHost,
            $src,
            $dst
        );
        $process = new Process($cmd);
        $process->setTimeout(null);
        $process->run($callback);
    }

    /**
     * Exec command on remote side via ssh
     * @param String $cmd Command
     */
    protected function execRemoteCommand($cmd)
    {
        // Prepare remote command
        $remoteHost = $this->getParam('remote.host');
        $remoteUser = $this->getParam('remote.user');
        $remoteDir = $this->getParam('remote.dir');
        $options = $this->getParam('ssh.options');

        // Check for ssh proxy
        $sshProxyString = $this->getSshProxyOption();
        if ($sshProxyString) {
            $options[] = $sshProxyString;
        }

        $remoteCmd = sprintf(
            'ssh %s %s@%s "cd %s ; %s 2>&1"',
            implode(' ', $options),
            $remoteUser,
            $remoteHost,
            $remoteDir,
            $cmd
        );
        $process = new Process($remoteCmd);
        $process->setTimeout(null);
        $process->run();
    }
}