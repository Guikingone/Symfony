<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Test;

use Symfony\Component\Scheduler\Event\TaskEventList;
use Symfony\Component\Scheduler\Test\Constraint\TaskScheduled;
use Symfony\Component\Scheduler\Test\Constraint\TaskExecuted;
use Symfony\Component\Scheduler\Test\Constraint\TaskFailed;
use Symfony\Component\Scheduler\Test\Constraint\TaskQueued;
use Symfony\Component\Scheduler\Test\Constraint\TaskUnscheduled;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.3
 */
trait SchedulerAssertionsTrait
{
    public static function assertTaskScheduledCount(int $count, string $message = ''): void
    {
        self::assertThat(self::getSchedulerEventList(), new TaskScheduled($count), $message);
    }

    public static function assertTaskUnscheduledCount(int $count, string $message = ''): void
    {
        self::assertThat(self::getSchedulerEventList(), new TaskUnscheduled($count), $message);
    }

    public static function assertTaskExecutedCount(int $count, string $message = ''): void
    {
        self::assertThat(self::getSchedulerEventList(), new TaskExecuted($count), $message);
    }

    public static function assertTaskQueuedCount(int $count, string $message = ''): void
    {
        self::assertThat(self::getSchedulerEventList(), new TaskQueued($count), $message);
    }

    public static function assertTaskFailedCount(int $count, string $message = ''): void
    {
        self::assertThat(self::getSchedulerEventList(), new TaskFailed($count), $message);
    }

    private static function getSchedulerEventList(): TaskEventList
    {
        if (self::$container->has('scheduler.task_logger.subscriber')) {
            return self::$container->get('mailer.task_logger.subscriber')->getEvents();
        }

        if (self::$container->has('scheduler.task_logger.subscriber')) {
            return self::$container->get('mailer.task_logger.subscriber')->getEvents();
        }

        static::fail('A client must have Scheduler enabled to make task assertions. Did you forget to require symfony/scheduler?');
    }
}
