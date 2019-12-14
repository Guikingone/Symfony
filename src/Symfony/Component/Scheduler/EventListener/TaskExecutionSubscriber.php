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
use Symfony\Component\Scheduler\Event\SingleRunTaskExecutedEvent;
use Symfony\Component\Scheduler\Event\TaskExecutedEvent;
use Symfony\Component\Scheduler\SchedulerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.2
 */
final class TaskExecutionSubscriber implements EventSubscriberInterface
{
    private $scheduler;

    public function __construct(SchedulerInterface $scheduler)
    {
        $this->scheduler = $scheduler;
    }

    public function onSingleRunTaskExecuted(SingleRunTaskExecutedEvent $event): void
    {
        $task = $event->getTask();

        $this->scheduler->unschedule($task->getName());
    }

    public function onTaskExecuted(TaskExecutedEvent $event): void
    {
        $task = $event->getTask();

        $this->scheduler->update($task->getName(), $task);
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            SingleRunTaskExecutedEvent::class => 'onSingleRunTaskExecuted',
            TaskExecutedEvent::class => 'onTaskExecuted',
        ];
    }
}
