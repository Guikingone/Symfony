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
use Symfony\Component\Scheduler\Event\TaskEventInterface;
use Symfony\Component\Scheduler\Event\TaskEventList;
use Symfony\Component\Scheduler\Event\TaskExecutedEvent;
use Symfony\Component\Scheduler\Event\TaskFailedEvent;
use Symfony\Component\Scheduler\Event\TaskScheduledEvent;
use Symfony\Component\Scheduler\Event\TaskUnscheduledEvent;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.3
 */
final class TaskLoggerSubscriber implements EventSubscriberInterface
{
    private $events;

    public function __construct()
    {
        $this->events = new TaskEventList();
    }

    public function onTask(TaskEventInterface $taskEvent): void
    {
        $this->events->addEvent($taskEvent);
    }

    public function getEvents(): TaskEventList
    {
        return $this->events;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            TaskExecutedEvent::class => ['onTask', -255],
            TaskFailedEvent::class => ['onTask', -255],
            TaskScheduledEvent::class => ['onTask', -255],
            TaskUnscheduledEvent::class => ['onTask', -255],
        ];
    }
}
