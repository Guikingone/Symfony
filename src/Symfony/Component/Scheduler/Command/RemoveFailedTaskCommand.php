<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Scheduler\SchedulerInterface;
use Symfony\Component\Scheduler\Task\TaskInterface;
use Symfony\Component\Scheduler\Worker\WorkerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.2
 */
final class RemoveFailedTaskCommand extends Command
{
    private $scheduler;
    private $worker;

    protected static $defaultName = 'scheduler:remove:failed';

    public function __construct(SchedulerInterface $scheduler, WorkerInterface $worker)
    {
        $this->scheduler = $scheduler;
        $this->worker = $worker;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setDefinition([
                new InputArgument('name', InputArgument::REQUIRED, 'The name of the task to remove'),
                new InputOption('force', 'f', InputOption::VALUE_NONE, 'Force the operation without confirmation'),
            ])
            ->setDescription('Remove given task from the scheduler')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $style = new SymfonyStyle($input, $output);

        $name = $input->getArgument('name');

        $toRemoveTask = $this->worker->getFailedTasks()->get($name);
        if (!$toRemoveTask instanceof TaskInterface) {
            $style->error(sprintf('The task "%s" does not fails', $name));

            return self::FAILURE;
        }

        if ($input->getOption('force') || $style->confirm('Do you want to permanently remove this task?', true)) {
            try {
                $this->scheduler->unschedule($toRemoveTask->getName());
            } catch (\Throwable $throwable) {
                $style->error([
                    'An error occurred when trying to unschedule the task:',
                    $throwable->getMessage(),
                ]);

                return self::FAILURE;
            }

            $style->success(sprintf('The task "%s" has been unscheduled', $toRemoveTask->getName()));

            return self::SUCCESS;
        } else {
            $style->note(sprintf('The task "%s" has not been unscheduled', $toRemoveTask->getName()));

            return self::FAILURE;
        }
    }
}
