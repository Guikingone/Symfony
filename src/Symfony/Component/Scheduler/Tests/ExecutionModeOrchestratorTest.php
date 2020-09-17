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
}
