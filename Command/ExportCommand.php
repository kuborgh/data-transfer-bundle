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

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 * Command to export (dump) the current database. This is called on the remote side of the fetch command
 */
class ExportCommand extends ContainerAwareCommand
{
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
        $siteaccess = $this->getContainer()->getParameter('data_transfer_bundle.siteaccess');
        $dbParams = $this->getContainer()->getParameter(sprintf('ezsettings.%s.database.params', $siteaccess));
        $dbName = $dbParams['database'];
        $dbUser = $dbParams['user'];
        $dbPass = $dbParams['password'];
        $dbHost = $dbParams['host'];

        // call mysqldump
        $cmd = sprintf(
            'mysqldump %s --user=%s --password=%s --host=%s 2>&1',
            escapeshellarg($dbName),
            escapeshellarg($dbUser),
            escapeshellarg($dbPass),
            escapeshellarg($dbHost)
        );

        // Execute command
        $process = new Process($cmd);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new \Exception(sprintf("Error dumping database:\n%s", $process->getOutput()));
        }

        $dump = $process->getOutput();

        // Output to console
        $output->writeln($dump);
    }
} 