<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Tests\DataCollector;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\DataCollector\SchedulerDataCollector;
use Symfony\Component\Scheduler\EventListener\TaskLoggerSubscriber;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SchedulerDataCollectorTest extends TestCase
{
    public function testSchedulerDataCollectorIsValid(): void
    {
        $logger = new TaskLoggerSubscriber();

        static::assertSame('scheduler', (new SchedulerDataCollector($logger))->getName());
    }

    public function testTasksCanBeCollected(): void
    {
        $logger = new TaskLoggerSubscriber();

        $dataCollector = new SchedulerDataCollector($logger);
        $dataCollector->lateCollect();

        static::assertEmpty($dataCollector->getEvents());
    }
}
