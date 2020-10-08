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
use Symfony\Component\Scheduler\Task\FailedTask;
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

        $event = new TaskScheduledEvent($task);

        $subscriber = new TaskLoggerSubscriber();
        $subscriber->onTask($event);

        static::assertNotEmpty($subscriber->getEvents()->getEvents());
        static::assertNotEmpty($subscriber->getEvents()->getScheduledTaskEvents());
        static::assertEmpty($subscriber->getEvents()->getFailedTaskEvents());
        static::assertEmpty($subscriber->getEvents()->getExecutedTaskEvents());
        static::assertEmpty($subscriber->getEvents()->getUnscheduledTaskEvents());
    }

    public function testExecutedTaskCanBeRetrieved(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $event = new TaskExecutedEvent($task);

        $subscriber = new TaskLoggerSubscriber();
        $subscriber->onTask($event);

        static::assertNotEmpty($subscriber->getEvents()->getEvents());
    }

    public function testFailedTaskCanBeRetrieved(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $failedTask = new FailedTask($task, 'error');

        $event = new TaskFailedEvent($failedTask);

        $subscriber = new TaskLoggerSubscriber();
        $subscriber->onTask($event);

        static::assertNotEmpty($subscriber->getEvents()->getEvents());
    }
}
