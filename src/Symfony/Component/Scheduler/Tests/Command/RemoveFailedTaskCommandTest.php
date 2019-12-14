<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Scheduler\Command\RemoveFailedTaskCommand;
use Symfony\Component\Scheduler\SchedulerInterface;
use Symfony\Component\Scheduler\Task\TaskInterface;
use Symfony\Component\Scheduler\Task\TaskListInterface;
use Symfony\Component\Scheduler\Worker\WorkerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class RemoveFailedTaskCommandTest extends TestCase
{
    public function testCommandIsConfigured(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);
        $worker = $this->createMock(WorkerInterface::class);

        $command = new RemoveFailedTaskCommand($scheduler, $worker);

        static::assertSame('scheduler:remove:failed', $command->getName());
        static::assertSame('Remove given task from the scheduler', $command->getDescription());
        static::assertTrue($command->getDefinition()->hasArgument('name'));
        static::assertSame('The name of the task to remove', $command->getDefinition()->getArgument('name')->getDescription());
        static::assertTrue($command->getDefinition()->getArgument('name')->isRequired());
        static::assertTrue($command->getDefinition()->hasOption('force'));
        static::assertSame('Force the operation without confirmation', $command->getDefinition()->getOption('force')->getDescription());
        static::assertSame('f', $command->getDefinition()->getOption('force')->getShortcut());
    }

    public function testCommandCannotRemoveUndefinedTask(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);

        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::once())->method('get')->willReturn(null);

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('getFailedTasks')->willReturn($taskList);

        $command = new RemoveFailedTaskCommand($scheduler, $worker);
        $tester = new CommandTester($command);
        $tester->execute([
            'name' => 'foo',
        ]);

        static::assertSame(Command::FAILURE, $tester->getStatusCode());
        static::assertStringContainsString('[ERROR] The task "foo" does not fails', $tester->getDisplay());
    }

    public function testCommandCannotRemoveTaskWithException(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('unschedule')->willThrowException(new \Exception('Random error'));

        $task = $this->createMock(TaskInterface::class);

        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::once())->method('get')->willReturn($task);

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('getFailedTasks')->willReturn($taskList);

        $command = new RemoveFailedTaskCommand($scheduler, $worker);
        $tester = new CommandTester($command);
        $tester->setInputs(['yes']);
        $tester->execute([
            'name' => 'foo',
        ]);

        static::assertSame(Command::FAILURE, $tester->getStatusCode());
        static::assertStringContainsString('[ERROR] An error occurred when trying to unschedule the task:', $tester->getDisplay());
        static::assertStringContainsString('Random error', $tester->getDisplay());
    }

    public function testCommandCanRemoveTaskWithForceOption(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('unschedule');

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(2))->method('getName')->willReturn('foo');

        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::once())->method('get')->willReturn($task);

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('getFailedTasks')->willReturn($taskList);

        $command = new RemoveFailedTaskCommand($scheduler, $worker);
        $tester = new CommandTester($command);
        $tester->execute([
            'name' => 'foo',
            '--force' => true,
        ]);

        static::assertSame(Command::SUCCESS, $tester->getStatusCode());
        static::assertStringContainsString('[OK] The task "foo" has been unscheduled', $tester->getDisplay());
    }

    public function testCommandCanRemoveTask(): void
    {
        $scheduler = $this->createMock(SchedulerInterface::class);
        $scheduler->expects(self::once())->method('unschedule');

        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::exactly(2))->method('getName')->willReturn('foo');

        $taskList = $this->createMock(TaskListInterface::class);
        $taskList->expects(self::once())->method('get')->willReturn($task);

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('getFailedTasks')->willReturn($taskList);

        $command = new RemoveFailedTaskCommand($scheduler, $worker);
        $tester = new CommandTester($command);
        $tester->setInputs(['yes']);
        $tester->execute([
            'name' => 'foo',
        ]);

        static::assertSame(Command::SUCCESS, $tester->getStatusCode());
        static::assertStringContainsString('[OK] The task "foo" has been unscheduled', $tester->getDisplay());
    }
}
