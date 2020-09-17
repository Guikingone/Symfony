<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Tests\SchedulePolicy;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\SchedulePolicy\DeadlinePolicy;
use Symfony\Component\Scheduler\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.3
 */
final class DeadlinePolicyTest extends TestCase
{
    public function testPolicySupport(): void
    {
        $policy = new DeadlinePolicy();

        static::assertFalse($policy->support('test'));
        static::assertTrue($policy->support('deadline'));
    }

    public function testTasksCanBeSorted(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getExecutionAbsoluteDeadline')->willReturn(new \DateInterval('P3D'));

        $secondTask = $this->createMock(TaskInterface::class);
        $secondTask->expects(self::once())->method('getExecutionAbsoluteDeadline')->willReturn(new \DateInterval('P2D'));

        $policy = new DeadlinePolicy();

        static::assertSame(['bar' => $task, 'foo' => $secondTask], $policy->sort(['foo' => $secondTask, 'bar' => $task]));
    }
}
