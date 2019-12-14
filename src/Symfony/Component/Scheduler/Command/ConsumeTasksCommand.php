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

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Scheduler\Event\TaskExecutedEvent;
use Symfony\Component\Scheduler\EventListener\StopWorkerOnFailureLimitSubscriber;
use Symfony\Component\Scheduler\EventListener\StopWorkerOnTaskLimitSubscriber;
use Symfony\Component\Scheduler\EventListener\StopWorkerOnTimeLimitSubscriber;
use Symfony\Component\Scheduler\SchedulerInterface;
use Symfony\Component\Scheduler\Worker\WorkerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.2
 */
final class ConsumeTasksCommand extends Command
{
    private $eventDispatcher;
    private $logger;
    private $scheduler;
    private $worker;

    /**
     * {@inheritdoc}
     */
    protected static $defaultName = 'scheduler:consume';

    public function __construct(SchedulerInterface $scheduler, WorkerInterface $worker, EventDispatcherInterface $eventDispatcher, LoggerInterface $logger = null)
    {
        $this->scheduler = $scheduler;
        $this->worker = $worker;
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = $logger ?: new NullLogger();

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setDescription('Consumes due tasks')
            ->setDefinition([
                new InputOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Limit the number of tasks consumed'),
                new InputOption('time-limit', 't', InputOption::VALUE_REQUIRED, 'Limit the time in seconds the worker can run'),
                new InputOption('failure-limit', 'f', InputOption::VALUE_REQUIRED, 'Limit the amount of task allowed to fail'),
            ])
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> command consumes due tasks.

    <info>php %command.full_name% <scheduler-name></info>

Use the --limit option to limit the number of tasks consumed:
    <info>php %command.full_name% <scheduler-name> --limit=10</info>

Use the --time-limit option to stop the worker when the given time limit (in seconds) is reached:
    <info>php %command.full_name% <scheduler-name> --time-limit=3600</info>

Use the --failure-limit option to stop the worker when the given amount of failed tasks is reached:
    <info>php %command.full_name% <scheduler-name> --time-limit=3600</info>
EOF
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $tasks = $this->scheduler->getDueTasks();
        if (0 === \count($tasks)) {
            $io->warning('No due tasks found');

            return self::SUCCESS;
        }

        $stopOptions = [];

        if (null !== $limit = $input->getOption('limit')) {
            $stopOptions[] = sprintf('%s tasks has been consumed', $limit);
            $this->eventDispatcher->addSubscriber(new StopWorkerOnTaskLimitSubscriber($limit, $this->logger));
        }

        if (null !== $timeLimit = $input->getOption('time-limit')) {
            $stopOptions[] = sprintf('it has been running for %d seconds', $timeLimit);
            $this->eventDispatcher->addSubscriber(new StopWorkerOnTimeLimitSubscriber($timeLimit, $this->logger));
        }

        if (null !== $failureLimit = $input->getOption('failure-limit')) {
            $stopOptions[] = sprintf('%d task%s have failed', $failureLimit, $failureLimit > 1 ? 's' : '');
            $this->eventDispatcher->addSubscriber(new StopWorkerOnFailureLimitSubscriber($failureLimit, $this->logger));
        }

        if ($stopOptions) {
            $last = array_pop($stopOptions);
            $stopsWhen = ($stopOptions ? implode(', ', $stopOptions).' or ' : '').$last;
            $io->comment(sprintf('The worker will automatically exit once %s.', $stopsWhen));
        }

        $tasksCount = \count($tasks);

        $io->note(sprintf('Found %d task%s', $tasksCount, $tasksCount > 1 ? 's' : ''));
        $io->comment('Quit the worker with CONTROL-C.');

        if (OutputInterface::VERBOSITY_VERBOSE > $output->getVerbosity()) {
            $io->comment(sprintf('The task%s output can be displayed if the -vv option is used', $tasksCount > 1 ? 's' : ''));
        }

        if ($output->isVeryVerbose()) {
            $this->eventDispatcher->addListener(TaskExecutedEvent::class, function (TaskExecutedEvent $event) use ($io): void {
                if (null === $output = $event->getOutput()) {
                    return;
                }

                $io->note('Task output:');
                $io->writeln($event->getOutput()->getOutput());
            });
        }

        try {
            $this->worker->execute();
        } catch (\Throwable $throwable) {
            $io->error([
                'An error occurred when executing the tasks',
                $throwable->getMessage()
            ]);

            return self::FAILURE;
        }

        $io->success(sprintf('%d task%s %s been consumed', $tasksCount, $tasksCount > 1 ? 's' : '', $tasksCount > 1 ? 'have' : 'has'));

        return self::SUCCESS;
    }
}
