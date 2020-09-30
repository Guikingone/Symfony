<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Worker;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Lock\BlockingStoreInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Store\FlockStore;
use Symfony\Component\Scheduler\Event\SingleRunTaskExecutedEvent;
use Symfony\Component\Scheduler\Event\TaskExecutedEvent;
use Symfony\Component\Scheduler\Event\TaskFailedEvent;
use Symfony\Component\Scheduler\Event\TaskExecutingEvent;
use Symfony\Component\Scheduler\Event\WorkerRestartedEvent;
use Symfony\Component\Scheduler\Event\WorkerRunningEvent;
use Symfony\Component\Scheduler\Event\WorkerStartedEvent;
use Symfony\Component\Scheduler\Event\WorkerStoppedEvent;
use Symfony\Component\Scheduler\Exception\UndefinedRunnerException;
use Symfony\Component\Scheduler\Runner\RunnerInterface;
use Symfony\Component\Scheduler\SchedulerInterface;
use Symfony\Component\Scheduler\Task\FailedTask;
use Symfony\Component\Scheduler\Task\TaskExecutionTrackerInterface;
use Symfony\Component\Scheduler\Task\TaskInterface;
use Symfony\Component\Scheduler\Task\TaskList;
use Symfony\Component\Scheduler\Task\TaskListInterface;
use Symfony\Contracts\EventDispatcher\Event;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.3
 */
final class Worker implements WorkerInterface
{
    private const DEFAULT_OPTIONS = [
        'sleep_duration_delay' => 1,
    ];

    private $runners;
    private $tracker;
    private $eventDispatcher;
    private $failedTasks;
    private $logger;
    private $running = false;
    private $shouldStop = false;
    private $store;
    private $scheduler;
    private $options;

    /**
     * @param iterable|RunnerInterface[]                      $runners
     * @param BlockingStoreInterface|PersistingStoreInterface $store
     */
    public function __construct(SchedulerInterface $scheduler, iterable $runners, TaskExecutionTrackerInterface $tracker, EventDispatcherInterface $eventDispatcher = null, LoggerInterface $logger = null, $store = null)
    {
        $this->scheduler = $scheduler;
        $this->runners = $runners;
        $this->tracker = $tracker;
        $this->store = $store;
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = $logger ?: new NullLogger();
        $this->failedTasks = new TaskList();
    }

    /**
     * {@inheritdoc}
     */
    public function execute(array $options = [], TaskInterface ...$tasks): void
    {
        if (empty($this->runners)) {
            throw new UndefinedRunnerException('No runner found');
        }

        $this->options = array_replace_recursive(self::DEFAULT_OPTIONS, $options);

        $this->dispatch(new WorkerStartedEvent($this));

        while (!$this->shouldStop) {
            if (!$tasks) {
                $tasks = $this->scheduler->getDueTasks();
            }

            foreach ($tasks as $task) {
                if (!$this->checkTaskState($task)) {
                    continue;
                }

                $this->dispatch(new WorkerRunningEvent($this));

                foreach ($this->runners as $runner) {
                    if (!$runner->support($task)) {
                        continue;
                    }

                    $this->handleSingleRunTask($task);
                    $lockedTask = $this->getLock($task);

                    try {
                        if ($lockedTask->acquire() && !$this->isRunning()) {
                            $this->running = true;
                            $this->dispatch(new WorkerRunningEvent($this));
                            $this->handleTask($runner, $task);
                        }
                    } catch (\Throwable $throwable) {
                        $failedTask = new FailedTask($task, $throwable->getMessage());
                        $this->failedTasks->add($failedTask);
                        $this->dispatch(new TaskFailedEvent($failedTask));
                    } finally {
                        $lockedTask->release();
                        $this->running = false;
                        $this->dispatch(new WorkerRunningEvent($this, true));
                    }

                    if ($this->shouldStop) {
                        break 3;
                    }
                }

                if ($this->shouldStop) {
                    break 2;
                }
            }

            sleep($this->getSleepDuration());

            $this->execute($options);
        }

        $this->dispatch(new WorkerStoppedEvent($this));
    }

    /**
     * {@inheritdoc}
     */
    public function restart(): void
    {
        $this->stop();
        $this->running = false;
        $this->failedTasks = new TaskList();
        $this->shouldStop = false;

        $this->dispatch(new WorkerRestartedEvent($this));
    }

    /**
     * {@inheritdoc}
     */
    public function stop(): void
    {
        $this->shouldStop = true;
    }

    /**
     * {@inheritdoc}
     */
    public function isRunning(): bool
    {
        return $this->running;
    }

    /**
     * {@inheritdoc}
     */
    public function getFailedTasks(): TaskListInterface
    {
        return $this->failedTasks;
    }

    private function checkTaskState(TaskInterface $task): bool
    {
        if (TaskInterface::UNDEFINED === $task->getState()) {
            throw new \LogicException('The task state must be defined in order to be executed!');
        }

        if (\in_array($task->getState(), [TaskInterface::PAUSED, TaskInterface::DISABLED])) {
            $this->logger->info(sprintf('The following task "%s" is paused|disabled, consider enable it if it should be executed!', $task->getName()));

            return false;
        }

        return true;
    }

    private function handleTask(RunnerInterface $runner, TaskInterface $task): void
    {
        $this->dispatch(new TaskExecutingEvent($task));

        $task->setArrivalTime(new \DateTimeImmutable());
        $task->setExecutionStartTime(new \DateTimeImmutable());
        $this->tracker->startTracking($task);
        $output = $runner->run($task);
        $this->tracker->endTracking($task);
        $task->setExecutionEndTime(new \DateTimeImmutable());
        $task->setLastExecution(new \DateTimeImmutable());

        $this->dispatch(new TaskExecutedEvent($task, $output));
    }

    private function handleSingleRunTask(TaskInterface $task): void
    {
        if (!$task->isSingleRun()) {
            return;
        }

        $this->dispatch(new SingleRunTaskExecutedEvent($task));
    }

    private function getLock(TaskInterface $task): LockInterface
    {
        if (null === $this->store) {
            $this->store = new FlockStore();
        }

        $factory = new LockFactory($this->store);

        return $factory->createLock($task->getName());
    }

    private function getSleepDuration(): int
    {
        $nextExecutionDate = new \DateTimeImmutable('+ 1 minute', $this->scheduler->getTimezone());
        $updatedNextExecutionDate = $nextExecutionDate->setTime((int) $nextExecutionDate->format('H'), (int) $nextExecutionDate->format('i'), 0);

        return (new \DateTimeImmutable('now', $this->scheduler->getTimezone()))->diff($updatedNextExecutionDate)->s + $this->options['sleep_duration_delay'];
    }

    private function dispatch(Event $event): void
    {
        if (null === $this->eventDispatcher) {
            return;
        }

        $this->eventDispatcher->dispatch($event);
    }
}
