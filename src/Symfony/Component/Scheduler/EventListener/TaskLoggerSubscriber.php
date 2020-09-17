<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Scheduler\Event\TaskExecutedEvent;
use Symfony\Component\Scheduler\Event\TaskFailedEvent;
use Symfony\Component\Scheduler\Event\TaskScheduledEvent;
use Symfony\Component\Scheduler\Task\TaskList;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.3
 */
final class TaskLoggerSubscriber implements EventSubscriberInterface
{
    private $scheduledTasks;
    private $executedTasks;
    private $failedTasks;

    public function __construct()
    {
        $this->scheduledTasks = new TaskList();
        $this->executedTasks = new TaskList();
        $this->failedTasks = new TaskList();
    }

    public function onTaskScheduled(TaskScheduledEvent $event): void
    {
        $this->scheduledTasks->add($event->getTask());
    }

    public function onTaskExecuted(TaskExecutedEvent $event): void
    {
        $this->executedTasks->add($event->getTask());
    }

    public function onTaskFailed(TaskFailedEvent $event): void
    {
        $this->failedTasks->add($event->getTask());
    }

    public function getTasks(): array
    {
        return [
            'scheduledTasks' => $this->scheduledTasks,
            'executedTasks' => $this->executedTasks,
            'failedTasks' => $this->failedTasks,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            TaskScheduledEvent::class => ['onTaskScheduled', -255],
            TaskExecutedEvent::class => ['onTaskExecuted', -255],
            TaskFailedEvent::class => ['onTaskFailed', -255],
        ];
    }
}
