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
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 * Command to export (dump) the current database. This is called on the remote side of the fetch command
 */
class ExportCommand extends ContainerAwareCommand
{
    use DatabaseConnectionTrait;

    /**
     * Configure command
     */
    protected function configure()
    {
        $this
            ->setName('data-transfer:export')
            ->setDescription('Dump SQL database to stdout');
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

        // call mysqldump
        $cmd = sprintf(
            'mysqldump %s --user=%s --password=%s --host=%s 2>&1',
            escapeshellarg($dbParams['dbName']),
            escapeshellarg($dbParams['dbUser']),
            escapeshellarg($dbParams['dbPass']),
            escapeshellarg($dbParams['dbHost'])
        );

        // Execute command
        $process = new Process($cmd);
        $process->setTimeout(null);

        // Output data directly to not get a timeout
        $process->run(
            function ($type, $buffer) use ($output) {
                // Output directly to console
                if ($type == Process::OUT) {
                    $output->write($buffer, false, Output::OUTPUT_RAW);
                }
            }
        );

        if (!$process->isSuccessful()) {
            throw new \Exception(sprintf("Error dumping database:\n%s", $process->getOutput()));
        }
    }
} 