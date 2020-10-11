<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\PhpUnit\ClockMock;
use Symfony\Component\Scheduler\Event\WorkerRunningEvent;
use Symfony\Component\Scheduler\Event\WorkerStartedEvent;
use Symfony\Component\Scheduler\Event\WorkerStoppedEvent;
use Symfony\Component\Scheduler\EventListener\StopWorkerOnTimeLimitSubscriber;
use Symfony\Component\Scheduler\Worker\WorkerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class StopWorkerOnTimeLimitSubscriberTest extends TestCase
{
    public function testSubscriberIsConfigured(): void
    {
        static::assertArrayHasKey(WorkerStartedEvent::class, StopWorkerOnTimeLimitSubscriber::getSubscribedEvents());
        static::assertArrayHasKey(WorkerStoppedEvent::class, StopWorkerOnTimeLimitSubscriber::getSubscribedEvents());
    }

    public function testSubscriberCannotWorkWithInvalidWorkerState(): void
    {
        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('isRunning')->willReturn(false);

        $subscriber = new StopWorkerOnTimeLimitSubscriber(1);
        $event = new WorkerStartedEvent($worker);

        $subscriber->onWorkerStarted($event);
    }

    public function testSubscriberCanWorkWithValidWorkerState(): void
    {
        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('isRunning')->willReturn(true);

        $subscriber = new StopWorkerOnTimeLimitSubscriber(1);
        $event = new WorkerStartedEvent($worker);

        $subscriber->onWorkerStarted($event);
    }

    public function testSubscriberCannotStopOnInvalidTime(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('info');

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('isRunning')->willReturn(true);
        $worker->expects(self::never())->method('stop');

        $subscriber = new StopWorkerOnTimeLimitSubscriber(1);
        $event = new WorkerRunningEvent($worker);
        $workerStartedEvent = new WorkerStartedEvent($worker);

        $subscriber->onWorkerStarted($workerStartedEvent);
        $subscriber->onWorkerRunning($event);
    }

    public function testSubscriberCannotStopOnValidTime(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info');

        $worker = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())->method('isRunning')->willReturn(true);
        $worker->expects(self::once())->method('stop');

        $subscriber = new StopWorkerOnTimeLimitSubscriber(1, $logger);
        $event = new WorkerRunningEvent($worker);
        $workerStartedEvent = new WorkerStartedEvent($worker);

        $subscriber->onWorkerStarted($workerStartedEvent);
        ClockMock::sleep(1);
        $subscriber->onWorkerRunning($event);
    }
}
