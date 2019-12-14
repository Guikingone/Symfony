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
use Symfony\Component\Scheduler\Event\TaskScheduledEvent;
use Symfony\Component\Scheduler\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.2
 */
final class TaskScheduledEventTest extends TestCase
{
    public function testEventReturnTask(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $event = new TaskScheduledEvent($task);
        static::assertSame($task, $event->getTask());
    }
}
