<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Tests\Worker;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Lock\BlockingStoreInterface;
use Symfony\Component\Scheduler\EventListener\StopWorkerOnTaskLimitSubscriber;
use Symfony\Component\Scheduler\Exception\UndefinedRunnerException;
use Symfony\Component\Scheduler\Runner\RunnerInterface;
use Symfony\Component\Scheduler\SchedulerInterface;
use Symfony\Component\Scheduler\Task\Output;
use Symfony\Component\Scheduler\Task\ShellTask;
use Symfony\Component\Scheduler\Task\TaskExecutionTrackerInterface;
use Symfony\Component\Scheduler\Task\TaskInterface;
use Symfony\Component\Scheduler\Task\TaskList;
use Symfony\Component\Scheduler\Worker\Worker;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class WorkerTest extends TestCase
{
    public function testTaskCannotBeExecutedWithoutRunner(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $watcher = $this->createMock(TaskExecutionTrackerInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $worker = new Worker($scheduler, [], $watcher, $eventDispatcher, $logger);

        static::expectException(UndefinedRunnerException::class);
        static::expectExceptionMessage('No runner found');
        $worker->execute();
    }

    public function testTaskCannotBeExecutedWithoutSupportingRunner(): void
    {
        $watcher = $this->createMock(TaskExecutionTrackerInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::never())->method('getTimezone');
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList([$task]));

        $runner = $this->createMock(RunnerInterface::class);
        $runner->expects(self::once())->method('support')->willReturn(false);

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new StopWorkerOnTaskLimitSubscriber(1));

        $worker = new Worker($scheduler, [$runner], $watcher, $eventDispatcher, $logger);
        $worker->execute();
    }

    public function testTaskCannotBeExecutedWhileStopped(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::exactly(2))->method('dispatch');

        $watcher = $this->createMock(TaskExecutionTrackerInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $runner = $this->createMock(RunnerInterface::class);
        $runner->expects(self::never())->method('support');

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::never())->method('getTimezone');
        $scheduler->expects(self::never())->method('getDueTasks');

        $worker = new Worker($scheduler, [$runner], $watcher, $eventDispatcher, $logger);
        $worker->stop();
        $worker->execute();
    }

    public function testTaskCanBeExecutedWithRunner(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('info');

        $tracker = $this->createMock(TaskExecutionTrackerInterface::class);
        $tracker->expects(self::once())->method('startTracking');
        $tracker->expects(self::once())->method('endTracking');

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(2))->method('getName')->willReturn('foo');
        $task->expects(self::exactly(2))->method('getState')->willReturn(TaskInterface::ENABLED);
        $task->expects(self::once())->method('isSingleRun')->willReturn(false);
        $task->expects(self::once())->method('setArrivalTime');
        $task->expects(self::once())->method('setExecutionStartTime');
        $task->expects(self::once())->method('setExecutionEndTime');
        $task->expects(self::once())->method('setLastExecution');

        $runner = $this->createMock(RunnerInterface::class);
        $runner->expects(self::once())->method('support')->with($task)->willReturn(true);
        $runner->expects(self::once())->method('run')->with($task)->willReturn(new Output($task, null));

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::never())->method('getTimezone');
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList([$task]));

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new StopWorkerOnTaskLimitSubscriber(1));

        $worker = new Worker($scheduler, [$runner], $tracker, $eventDispatcher, $logger);
        $worker->execute();
    }

    public function testTaskCannotBeExecutedTwiceAsSingleRunTask(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $store = $this->createMock(BlockingStoreInterface::class);
        $tracker = $this->createMock(TaskExecutionTrackerInterface::class);

        $task = new ShellTask('foo', ['echo', 'Symfony']);
        $task->setExpression('* * * * *');
        $task->setSingleRun(true);

        $runner = $this->createMock(RunnerInterface::class);
        $runner->expects(self::once())->method('support')->with($task)->willReturn(true);
        $runner->expects(self::once())->method('run')->with($task)->willReturn(new Output($task, null));

        $secondRunner = $this->createMock(RunnerInterface::class);
        $secondRunner->expects(self::never())->method('support')->willReturn(true);
        $secondRunner->expects(self::never())->method('run')->willReturn(new Output($task, null));

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::never())->method('getTimezone');
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList([$task]));

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new StopWorkerOnTaskLimitSubscriber(2));

        $worker = new Worker($scheduler, [$runner, $secondRunner], $tracker, $eventDispatcher, $logger, $store);
        $worker->execute();
    }

    public function testWorkerCanHandleFailedTask(): void
    {
        $runner = $this->createMock(RunnerInterface::class);
        $runner->expects(self::once())->method('support')->willReturn(true);
        $runner->expects(self::once())->method('run')->willThrowException(new \RuntimeException('Random error occurred'));

        $tracker = $this->createMock(TaskExecutionTrackerInterface::class);
        $tracker->expects(self::once())->method('startTracking');
        $tracker->expects(self::never())->method('endTracking');

        $logger = $this->createMock(LoggerInterface::class);

        $task = $this->createMock(TaskInterface::class);
        $task->method('getName')->willReturn('failed');

        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::never())->method('getTimezone');
        $scheduler->expects(self::once())->method('getDueTasks')->willReturn(new TaskList([$task]));

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new StopWorkerOnTaskLimitSubscriber(1));

        $worker = new Worker($scheduler, [$runner], $tracker, $eventDispatcher, $logger);
        $worker->execute();

        static::assertNotEmpty($worker->getFailedTasks());
        static::assertCount(1, $worker->getFailedTasks());
        static::assertSame('Random error occurred', $worker->getFailedTasks()->get('failed.failed')->getReason());
    }
}
