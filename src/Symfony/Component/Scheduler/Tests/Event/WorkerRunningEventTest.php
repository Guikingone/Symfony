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
use Symfony\Component\Scheduler\Event\WorkerRunningEvent;
use Symfony\Component\Scheduler\Worker\WorkerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.3
 */
final class WorkerRunningEventTest extends TestCase
{
    public function testWorkerCanBeRetrieved(): void
    {
        $worker = $this->createMock(WorkerInterface::class);

        $event = new WorkerRunningEvent($worker);
        static::assertSame($worker, $event->getWorker());
    }
}
