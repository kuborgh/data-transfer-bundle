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
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 * Command to export (dump) the current database. This is called on the remote side of the fetch command
 */
class ExportCommand extends ContainerAwareCommand
{
    use DatabaseConnectionTrait;

    const OPT_FILE = 'file';

    /**
     * Configure command
     */
    protected function configure()
    {
        $this
            ->setName('data-transfer:export')
            ->setDescription('Dump SQL database to stdout');
        $this->addOption(self::OPT_FILE, null, InputOption::VALUE_NONE, 'Dump database to file, not to stdout');
    }

    /**
     * Execute the command
     *
     * @param \Symfony\Component\Console\Input\InputInterface   $input  Input
     * @param \Symfony\Component\Console\Output\OutputInterface $output Output
     *
     * @throws \Exception
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Fetch db connection data
        $dbParams = $this->getDatabaseParameter();

        // Prepare command line parameters
        $parameters = Array();
        $parameters[] = escapeshellarg($dbParams['dbName']);
        $parameters[] = sprintf('--user=%s', escapeshellarg($dbParams['dbUser']));
        $parameters[] = sprintf('--password=%s', escapeshellarg($dbParams['dbPass']));
        $parameters[] = sprintf('--host=%s', escapeshellarg($dbParams['dbHost']));

        if (!empty($dbParams['databaseExportArguments'])) {
            $parameters[] = implode(' ', array_map('escapeshellarg', explode(' ', $dbParams['databaseExportArguments'])));
        }

        // Write to file instead of stdout
        $toFile = $input->getOption(self::OPT_FILE);
        $filename = null;
        if ($toFile) {
            $folder = $this->getContainer()->getParameter('kernel.cache_dir');
            $filename = sprintf('%s/db-dump-%s.sql', $folder, time());
            $parameters[] = escapeshellarg('-q');
            $parameters[] = escapeshellarg(sprintf('--result-file=%s', $filename));

            // cleanup old dumps to make some space for new ones
            $this->cleanupOldDumps();
        }

        // call mysqldump
        $cmd = sprintf('mysqldump %s 2>&1', implode(' ', $parameters));

        // Execute command
        $process = new Process($cmd);
        $process->setTimeout(null);

        // Output data directly to not get a timeout
        $process->run(
            function ($type, $buffer) use ($output, $toFile) {
                // Output directly to console
                if (!$toFile && $type == Process::OUT) {
                    $output->write($buffer, false, Output::OUTPUT_RAW);
                }
            }
        );

        if (!$process->isSuccessful()) {
            throw new \Exception(sprintf("Error dumping database:\n%s", $process->getOutput()));
        }

        // Print hints to the file
        if ($toFile) {
            $data = Array();
            $data['filename'] = $filename;
            $data['basename'] = basename($filename);
            $data['size'] = filesize($filename);
            $output->write(json_encode($data), false, Output::OUTPUT_RAW);
        }
    }

    /**
     * Cleanup db dump from cache folder, that are older than 24h
     */
    protected function cleanupOldDumps()
    {
        // Define "old" as 24h in the past
        $old = time() - 24 * 60 * 60;

        $folder = $this->getContainer()->getParameter('kernel.cache_dir');
        foreach (glob($folder . '/db-dump-*.sql') as $dump) {
            if (!preg_match('/db\-dump\-(\d*)\.sql$/', $dump, $matches)) {
                continue;
            }
            if ($matches[1] < $old) {
                $process = new Process(sprintf('rm %s', $dump));
                $process->run();
            }
        }
    }
} 