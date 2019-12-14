<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Tests\Messenger;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\Messenger\TaskMessage;
use Symfony\Component\Scheduler\Messenger\TaskMessageHandler;
use Symfony\Component\Scheduler\Task\ShellTask;
use Symfony\Component\Scheduler\Worker\WorkerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @group time-sensitive
 */
final class TaskMessageHandlerTest extends TestCase
{
    public function testHandlerCanRunDueTask(): void
    {
        $task = new ShellTask('foo', ['echo', 'Symfony']);
        $task->setScheduledAt(new \DateTimeImmutable());
        $task->setExpression('* * * * *');
        $task->setTimezone(new \DateTimeZone('UTC'));

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('isRunning')->willReturn(false);
        $worker->expects(self::once())->method('execute');

        $handler = new TaskMessageHandler($worker);

        ($handler)(new TaskMessage($task));
    }
}
