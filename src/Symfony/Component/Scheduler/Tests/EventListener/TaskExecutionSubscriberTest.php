<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\Event\SingleRunTaskExecutedEvent;
use Symfony\Component\Scheduler\Event\TaskExecutedEvent;
use Symfony\Component\Scheduler\EventListener\TaskExecutionSubscriber;
use Symfony\Component\Scheduler\SchedulerInterface;
use Symfony\Component\Scheduler\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskExecutionSubscriberTest extends TestCase
{
    public function testSubscriberListenValidEvent(): void
    {
        static::assertArrayHasKey(SingleRunTaskExecutedEvent::class, TaskExecutionSubscriber::getSubscribedEvents());
        static::assertArrayHasKey(TaskExecutedEvent::class, TaskExecutionSubscriber::getSubscribedEvents());
    }

    public function testSubscriberCanUnscheduleSingleRunTask(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('unschedule')->with(self::equalTo('foo'));

        $event = new SingleRunTaskExecutedEvent($task);

        $subscriber = new TaskExecutionSubscriber($scheduler);
        $subscriber->onSingleRunTaskExecuted($event);
    }

    public function testSubscriberCanUpdateExecutedTask(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('update')->with(self::equalTo('foo'), $task);

        $event = new TaskExecutedEvent($task);

        $subscriber = new TaskExecutionSubscriber($scheduler);
        $subscriber->onTaskExecuted($event);
    }
}
