<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\ExecutionModeOrchestrator;
use Symfony\Component\Scheduler\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ExecutionModeOrchestratorTest extends TestCase
{
    public function testListCannotBeSortedWithInvalidMode(): void
    {
        static::expectException(\LogicException::class);
        static::expectExceptionMessage('The given mode "test" is not a valid one, allowed ones are: "round_robin, deadline, batch, first_in_first_out, idle, normal"');
        new ExecutionModeOrchestrator('test');
    }

    public function testListCanBeUsingDefaultMode(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getPriority')->willReturn(1);

        $secondTask = $this->createMock(TaskInterface::class);
        $secondTask->expects(self::exactly(2))->method('getPriority')->willReturn(2);

        $orchestrator = new ExecutionModeOrchestrator();
        $tasks = $orchestrator->sort(['app' => $secondTask, 'foo' => $task]);

        static::assertSame(['app' => $secondTask, 'foo' => $task], $tasks);
    }

    public function testListCanBeUsingRoundFifoMode(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->method('getPriority')->willReturn(200);

        $secondTask = $this->createMock(TaskInterface::class);
        $secondTask->method('getPriority')->willReturn(100);

        $thirdTask = $this->createMock(TaskInterface::class);
        $thirdTask->method('getPriority')->willReturn(75);

        $orchestrator = new ExecutionModeOrchestrator();
        $tasks = $orchestrator->sort(['app' => $secondTask, 'foo' => $task, 'bar' => $thirdTask]);

        static::assertSame(['foo' => $task, 'app' => $secondTask, 'bar' => $thirdTask], $tasks);
    }

    public function testListCanBeUsingRoundRobinTimedMode(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getExecutionComputationTime')->willReturn(12.0);

        $secondTask = $this->createMock(TaskInterface::class);
        $secondTask->expects(self::exactly(2))->method('getExecutionComputationTime')->willReturn(10.0);
        $secondTask->expects(self::once())->method('getMaxDuration')->willReturn(10.0);

        $orchestrator = new ExecutionModeOrchestrator(ExecutionModeOrchestrator::ROUND_ROBIN);
        $tasks = $orchestrator->sort(['foo' => $secondTask, 'bar' => $task]);

        static::assertSame(['bar' => $task, 'foo' => $secondTask], $tasks);
    }

    public function testListCanBeUsingDeadlineMode(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getExecutionAbsoluteDeadline')->willReturn(new \DateInterval('P3D'));

        $secondTask = $this->createMock(TaskInterface::class);
        $secondTask->expects(self::once())->method('getExecutionAbsoluteDeadline')->willReturn(new \DateInterval('P2D'));

        $orchestrator = new ExecutionModeOrchestrator(ExecutionModeOrchestrator::DEADLINE);
        $tasks = $orchestrator->sort(['foo' => $secondTask, 'bar' => $task]);

        static::assertSame(['bar' => $task, 'foo' => $secondTask], $tasks);
    }

    public function testListCanBeUsingBatchMode(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->method('getPriority')->willReturnOnConsecutiveCalls(2, 1);

        $secondTask = $this->createMock(TaskInterface::class);
        $secondTask->method('getPriority')->willReturnOnConsecutiveCalls(2, 1);

        $orchestrator = new ExecutionModeOrchestrator(ExecutionModeOrchestrator::BATCH);
        $tasks = $orchestrator->sort([$secondTask, $task]);

        static::assertCount(2, $tasks);
    }

    public function testListCanBeUsingIdleMode(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getPriority')->willReturn(-10);

        $secondTask = $this->createMock(TaskInterface::class);
        $secondTask->expects(self::exactly(2))->method('getPriority')->willReturn(-20);

        $orchestrator = new ExecutionModeOrchestrator(ExecutionModeOrchestrator::IDLE);
        $tasks = $orchestrator->sort(['app' => $secondTask, 'foo' => $task]);

        static::assertCount(2, $tasks);
        static::assertSame(['foo' => $task, 'app' => $secondTask], $tasks);
    }
}
