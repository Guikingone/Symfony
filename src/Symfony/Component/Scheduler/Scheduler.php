<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler;

use Cron\CronExpression;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Scheduler\Event\SchedulerRebootedEvent;
use Symfony\Component\Scheduler\Event\TaskScheduledEvent;
use Symfony\Component\Scheduler\Event\TaskUnscheduledEvent;
use Symfony\Component\Scheduler\Exception\RuntimeException;
use Symfony\Component\Scheduler\Expression\ExpressionFactory;
use Symfony\Component\Scheduler\Messenger\TaskMessage;
use Symfony\Component\Scheduler\Task\TaskInterface;
use Symfony\Component\Scheduler\Task\TaskListInterface;
use Symfony\Component\Scheduler\Transport\TransportInterface;
use Symfony\Contracts\EventDispatcher\Event;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.3
 */
final class Scheduler implements SchedulerInterface
{
    private const MIN_SYNCHRONIZATION_DELAY = 1000000;
    private const MAX_SYNCHRONIZATION_DELAY = 86400000000;

    private $initializationDate;
    private $timezone;
    private $transport;
    private $eventDispatcher;
    private $bus;

    public function __construct(\DateTimeZone $timezone, TransportInterface $transport, EventDispatcherInterface $eventDispatcher = null, MessageBusInterface $bus = null)
    {
        $this->initializationDate = new \DateTimeImmutable('now', $timezone);
        $this->timezone = $timezone;
        $this->transport = $transport;
        $this->eventDispatcher = $eventDispatcher;
        $this->bus = $bus;
    }

    /**
     * {@inheritdoc}
     */
    public function schedule(TaskInterface $task): void
    {
        $synchronizedCurrentDate = $this->getSynchronizedCurrentDate();

        $task->setScheduledAt($synchronizedCurrentDate);
        $task->setTimezone($this->timezone);

        if (null !== $this->bus && $task->isQueued()) {
            $this->bus->dispatch(new TaskMessage($task));
            $this->dispatch(new TaskScheduledEvent($task));

            return;
        }

        $this->transport->create($task);
        $this->dispatch(new TaskScheduledEvent($task));
    }

    /**
     * {@inheritdoc}
     */
    public function unschedule(string $taskName): void
    {
        $this->transport->delete($taskName);
        $this->dispatch(new TaskUnscheduledEvent($taskName));
    }

    /**
     * {@inheritdoc}
     */
    public function update(string $taskName, TaskInterface $task): void
    {
        $this->transport->update($taskName, $task);
    }

    /**
     * {@inheritdoc}
     */
    public function pause(string $taskName): void
    {
        $this->transport->pause($taskName);
    }

    /**
     * {@inheritdoc}
     */
    public function resume(string $taskName): void
    {
        $this->transport->resume($taskName);
    }

    /**
     * {@inheritdoc}
     */
    public function getDueTasks(): TaskListInterface
    {
        $synchronizedCurrentDate = $this->getSynchronizedCurrentDate();

        return $this->transport->list()->filter(function (TaskInterface $task) use ($synchronizedCurrentDate): bool {
            return CronExpression::factory($task->getExpression())->isDue($synchronizedCurrentDate, $task->getTimezone()->getName());
        });
    }

    /**
     * {@inheritdoc}
     */
    public function getTimezone(): \DateTimeZone
    {
        return $this->timezone;
    }

    /**
     * {@inheritdoc}
     */
    public function getTasks(): TaskListInterface
    {
        return $this->transport->list();
    }

    /**
     * {@inheritdoc}
     */
    public function reboot(): void
    {
        $rebootTasks = $this->getTasks()->filter(function (TaskInterface $task): bool {
            return ExpressionFactory::REBOOT_MACRO === $task->getExpression();
        });

        $this->transport->clear();

        foreach ($rebootTasks as $task) {
            $this->transport->create($task);
        }

        $this->dispatch(new SchedulerRebootedEvent($this));
    }

    private function dispatch(Event $event): void
    {
        if (null === $this->eventDispatcher) {
            return;
        }

        $this->eventDispatcher->dispatch($event);
    }

    private function getSynchronizedCurrentDate(): \DateTimeImmutable
    {
        $initializationDelay = $this->initializationDate->diff(new \DateTimeImmutable('now', $this->timezone));
        if ($initializationDelay->f % self::MIN_SYNCHRONIZATION_DELAY < 0 || $initializationDelay->f % self::MAX_SYNCHRONIZATION_DELAY > 0) {
            throw new RuntimeException(sprintf(
                'The scheduler is not synchronized with the current clock, current delay: %d microseconds, allowed range: [%s, %s]',
                $initializationDelay->f,
                self::MIN_SYNCHRONIZATION_DELAY,
                self::MAX_SYNCHRONIZATION_DELAY
            ));
        }

        return $this->initializationDate->add($initializationDelay);
    }
}
