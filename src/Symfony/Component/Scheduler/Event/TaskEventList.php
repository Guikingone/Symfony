<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Event;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.3
 */
final class TaskEventList implements \Countable
{
    /**
     * @var TaskEventInterface[]
     */
    private $events = [];

    public function addEvent(TaskEventInterface $event): void
    {
        $this->events[] = $event;
    }

    public function getEvents(): array
    {
        return $this->events;
    }

    public function getScheduledTaskEvents(): array
    {
        return array_filter($this->events, function (TaskEventInterface $event): bool {
            return $event instanceof TaskScheduledEvent;
        });
    }

    public function getUnscheduledTaskEvents(): array
    {
        return array_filter($this->events, function (TaskEventInterface $event): bool {
            return $event instanceof TaskUnscheduledEvent;
        });
    }

    public function getExecutedTaskEvents(): array
    {
        return array_filter($this->events, function (TaskEventInterface $event): bool {
            return $event instanceof TaskExecutedEvent;
        });
    }

    public function getFailedTaskEvents(): array
    {
        return array_filter($this->events, function (TaskEventInterface $event): bool {
            return $event instanceof TaskFailedEvent;
        });
    }

    public function getQueuedTaskEvents(): array
    {
        return array_filter($this->events, function (TaskEventInterface $event): bool {
            return $event instanceof TaskScheduledEvent && $event->getTask()->isQueued();
        });
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        return \count($this->events);
    }
}
