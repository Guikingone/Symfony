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
use Symfony\Component\Scheduler\Event\TaskExecutedEvent;
use Symfony\Component\Scheduler\Event\TaskFailedEvent;
use Symfony\Component\Scheduler\Event\TaskScheduledEvent;
use Symfony\Component\Scheduler\EventListener\TaskLoggerSubscriber;
use Symfony\Component\Scheduler\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskLoggerSubscriberTest extends TestCase
{
    public function testEventsAreSubscribed(): void
    {
        static::assertArrayHasKey(TaskScheduledEvent::class, TaskLoggerSubscriber::getSubscribedEvents());
        static::assertArrayHasKey(TaskExecutedEvent::class, TaskLoggerSubscriber::getSubscribedEvents());
    }

    public function testScheduledTaskCanBeRetrieved(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');

        $event = new TaskScheduledEvent($task);

        $subscriber = new TaskLoggerSubscriber();
        $subscriber->onTaskScheduled($event);

        static::assertNotEmpty($subscriber->getTasks());
        static::assertSame($task, $subscriber->getTasks()['scheduledTasks']->get('foo'));
    }

    public function testExecutedTaskCanBeRetrieved(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');

        $event = new TaskExecutedEvent($task);

        $subscriber = new TaskLoggerSubscriber();
        $subscriber->onTaskExecuted($event);

        static::assertNotEmpty($subscriber->getTasks());
        static::assertSame($task, $subscriber->getTasks()['executedTasks']->get('foo'));
    }

    public function testFailedTaskCanBeRetrieved(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');

        $event = new TaskFailedEvent($task);

        $subscriber = new TaskLoggerSubscriber();
        $subscriber->onTaskFailed($event);

        static::assertNotEmpty($subscriber->getTasks());
        static::assertSame($task, $subscriber->getTasks()['failedTasks']->get('foo'));
    }
}
