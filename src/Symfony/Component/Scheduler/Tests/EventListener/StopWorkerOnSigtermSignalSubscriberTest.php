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
use Symfony\Component\Scheduler\Event\WorkerRunningEvent;
use Symfony\Component\Scheduler\Event\WorkerStartedEvent;
use Symfony\Component\Scheduler\EventListener\StopWorkerOnSigtermSignalSubscriber;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @requires extension pcntl
 */
final class StopWorkerOnSigtermSignalSubscriberTest extends TestCase
{
    public function testEventsAreSubscribed(): void
    {
        static::assertArrayHasKey(WorkerStartedEvent::class, StopWorkerOnSigtermSignalSubscriber::getSubscribedEvents());
        static::assertArrayHasKey(WorkerRunningEvent::class, StopWorkerOnSigtermSignalSubscriber::getSubscribedEvents());
    }
}
