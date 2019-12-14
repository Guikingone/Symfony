<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Tests\Event;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\Event\TaskEventList;
use Symfony\Component\Scheduler\Event\TaskExecutedEvent;
use Symfony\Component\Scheduler\Event\TaskFailedEvent;
use Symfony\Component\Scheduler\Event\TaskScheduledEvent;
use Symfony\Component\Scheduler\Event\TaskUnscheduledEvent;
use Symfony\Component\Scheduler\Task\FailedTask;
use Symfony\Component\Scheduler\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskEventListTest extends TestCase
{
    public function testEventCanBeAdded(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $list = new TaskEventList();
        $list->addEvent(new TaskExecutedEvent($task));

        static::assertNotEmpty($list->getEvents());
        static::assertSame(1, $list->count());
        static::assertEmpty($list->getScheduledTaskEvents());
        static::assertEmpty($list->getFailedTaskEvents());
        static::assertNotEmpty($list->getExecutedTaskEvents());
        static::assertEmpty($list->getUnscheduledTaskEvents());
        static::assertEmpty($list->getQueuedTaskEvents());
    }

    public function testScheduledTaskEventsCanBeRetrieved(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $list = new TaskEventList();
        $list->addEvent(new TaskScheduledEvent($task));

        static::assertNotEmpty($list->getScheduledTaskEvents());
        static::assertSame($task, $list->getScheduledTaskEvents()[0]->getTask());
    }

    public function testUnscheduledTaskEventsCanBeRetrieved(): void
    {
        $list = new TaskEventList();
        $list->addEvent(new TaskUnscheduledEvent('foo'));

        static::assertNotEmpty($list->getUnscheduledTaskEvents());
        static::assertSame('foo', $list->getUnscheduledTaskEvents()[0]->getTask());
    }

    public function testExecutedTaskEventsCanBeRetrieved(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $list = new TaskEventList();
        $list->addEvent(new TaskExecutedEvent($task));

        static::assertNotEmpty($list->getExecutedTaskEvents());
        static::assertSame($task, $list->getExecutedTaskEvents()[0]->getTask());
    }

    public function testFailedTaskEventsCanBeRetrieved(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $failedTask = new FailedTask($task, 'error');

        $list = new TaskEventList();
        $list->addEvent(new TaskFailedEvent($failedTask));

        static::assertNotEmpty($list->getFailedTaskEvents());
        static::assertSame($failedTask, $list->getFailedTaskEvents()[0]->getTask());
        static::assertSame($task, $list->getFailedTaskEvents()[0]->getTask()->getTask());
    }

    public function testQueuedTaskEventsCanBeRetrieved(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(2))->method('isQueued')->willReturn(true);

        $list = new TaskEventList();
        $list->addEvent(new TaskScheduledEvent($task));

        static::assertNotEmpty($list->getQueuedTaskEvents());
        static::assertSame($task, $list->getQueuedTaskEvents()[0]->getTask());
    }
}
