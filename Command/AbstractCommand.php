<?php

/*
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

/**
 * Abstraction for all commands, implementing a progress bar and a summary table
 */
abstract class AbstractCommand extends ContainerAwareCommand
{
    const ROW_LIMIT = 50;

    /**
     * Internal Counter for progress display
     *
     * @var int
     */
    protected $progressCount = 0;

    /**
     * Make output available class-wide
     *
     * @var OutputInterface
     */
    protected $output = null;

    /**
     * Save errors to be printed in summary
     *
     * @var array
     */
    protected $errors = Array();

    /**
     * Save the output as class attribute to do some funny output stuff
     *
     * @param \Symfony\Component\Console\Input\InputInterface   $input  Input
     * @param \Symfony\Component\Console\Output\OutputInterface $output Output
     *
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;
    }

    /**
     * Print progress (optionally with custom char)
     *
     * @param String $char
     */
    protected function progress($char = '.')
    {
        $this->progressCount++;
        $this->output->write($char);
        $this->progressRowUpdate();
    }

    /**
     * Print progress (optionally with custom char)
     *
     * @param String $char
     */
    protected function progressOk($char = '.')
    {
        $this->progressCount++;
        $this->output->write(sprintf('<info>%s</info>', $char));
        $this->progressRowUpdate();
    }

    /**
     * Print error in progress and save message for later output
     *
     * @param String $message
     */
    protected function progressErr($message = '')
    {
        $this->progressCount++;
        $this->output->write(sprintf('<error>E</error>'));
        // remember message only, when set
        if ($message) {
            $this->errors[] = $message;
        }
        $this->progressRowUpdate();
    }

    /**
     * Print progress done message and clear the row
     */
    protected function progressDone()
    {
        // End the row and reset counter
        $this->output->writeln(
            sprintf('%s <info>done</info>', str_repeat(' ', self::ROW_LIMIT - ($this->progressCount % self::ROW_LIMIT)))
        );
        $this->progressCount = 0;

        // Print errors
        foreach ($this->errors as $err) {
            $this->output->writeln(sprintf('<error>%s</error>', $err));
        }
        $this->errors = Array();
    }

    /**
     * Update row after each progress step
     */
    protected function progressRowUpdate()
    {
        // Start new line, when limit is reached
        if ($this->progressCount % self::ROW_LIMIT == 0) {
            $this->output->writeln(sprintf(' %4d', $this->progressCount));
        }
    }

}