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
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Scheduler\Expression\ExpressionFactory;
use Symfony\Component\Scheduler\SchedulerInterface;
use Symfony\Component\Scheduler\Task\TaskInterface;
use Symfony\Component\Scheduler\Worker\WorkerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.2
 */
final class RebootSchedulerCommand extends Command
{
    private $scheduler;
    private $worker;

    protected static $defaultName = 'scheduler:reboot';

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
                new InputOption('dry-run', 'd', InputOption::VALUE_NONE, 'Test the reboot without executing the tasks, the "ready to reboot" tasks are displayed')
            ])
            ->setDescription('Reboot the scheduler')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($input->getOption('dry-run')) {
            $tasks = $this->scheduler->getTasks()->filter(function (TaskInterface $task): bool {
                return ExpressionFactory::REBOOT_MACRO === $task->getExpression();
            });
            if (empty($tasks)) {
                $io->warning('The scheduler does not contain any tasks planned for the reboot process');

                return self::SUCCESS;
            }

            $table = new Table($output);
            $table->setHeaders(['Name', 'Type', 'State', 'Tags']);

            foreach ($tasks as $task) {
                $table->addRow([$task->getName(), get_class($task), $task->getState(), implode(', ', $task->getTags())]);
            }

            $io->success('The following tasks will be executed when the scheduler will reboot:');
            $table->render();

            return self::SUCCESS;
        }

        $this->scheduler->reboot();

        $tasks = $this->scheduler->getTasks()->filter(function (TaskInterface $task): bool {
            return ExpressionFactory::REBOOT_MACRO === $task->getExpression();
        });
        if (empty($tasks)) {
            $io->success('The scheduler have been rebooted');

            return self::SUCCESS;
        }

        while ($this->worker->isRunning()) {
            $io->warning('The scheduler cannot be rebooted as the worker is not available, retrying to access it');
            sleep(1);
        }

        foreach ($tasks as $rebootTask) {
            $this->worker->execute($rebootTask);
        }

        $io->success('The scheduler have been rebooted, the following tasks have been executed');

        $table = new Table($output);
        $table->setHeaders(['Name', 'Type', 'State', 'Tags']);

        foreach ($tasks as $task) {
            $table->addRow([$task->getName(), get_class($task), $task->getState(), implode(', ', $task->getTags())]);
        }

        $table->render();

        return self::SUCCESS;
    }
}
