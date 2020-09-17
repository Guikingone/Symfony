<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Tests\Runner;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\Runner\CallbackTaskRunner;
use Symfony\Component\Scheduler\Task\CallbackTask;
use Symfony\Component\Scheduler\Task\Output;
use Symfony\Component\Scheduler\Task\ShellTask;
use Symfony\Component\Scheduler\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class CallBackTaskRunnerTest extends TestCase
{
    public function testRunnerCannotSupportInvalidTask(): void
    {
        $runner = new CallbackTaskRunner();

        $task = new ShellTask('foo', ['echo', 'Symfony!']);
        static::assertFalse($runner->support($task));

        $task = new CallbackTask('foo', function () {
            return 1 + 1;
        });
        static::assertTrue($runner->support($task));
    }

    public function testRunnerCanExecuteValidTask(): void
    {
        $runner = new CallbackTaskRunner();
        $task = new CallbackTask('foo', function () {
            return 1 + 1;
        });

        static::assertInstanceOf(Output::class, $runner->run($task));
        static::assertSame('2', $runner->run($task)->getOutput());
        static::assertSame(TaskInterface::SUCCEED, $runner->run($task)->getTask()->getExecutionState());
    }

    public function testRunnerCanExecuteValidTaskWithArguments(): void
    {
        $runner = new CallbackTaskRunner();
        $task = new CallbackTask('foo', function ($a, $b) {
            return $a * $b;
        }, [1, 2]);

        static::assertInstanceOf(Output::class, $runner->run($task));
        static::assertSame('2', $runner->run($task)->getOutput());
        static::assertSame(TaskInterface::SUCCEED, $runner->run($task)->getTask()->getExecutionState());
    }

    public function testRunnerCanExecuteInValidTask(): void
    {
        $runner = new CallbackTaskRunner();
        $task = new CallbackTask('foo', function ($a, $b) {
            return $a * $b;
        }, [1]);

        static::assertInstanceOf(Output::class, $runner->run($task));
        static::assertNull($runner->run($task)->getOutput());
        static::assertSame(TaskInterface::ERRORED, $runner->run($task)->getTask()->getExecutionState());
    }
}
