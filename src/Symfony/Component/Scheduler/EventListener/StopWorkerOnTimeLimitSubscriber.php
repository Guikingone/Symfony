<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\EventListener;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Scheduler\Event\WorkerStartedEvent;
use Symfony\Component\Scheduler\Event\WorkerStoppedEvent;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.3
 */
final class StopWorkerOnTimeLimitSubscriber implements EventSubscriberInterface
{
    private $endTime;
    private $logger;
    private $timeLimitInSeconds;

    public function __construct(int $timeLimitInSeconds, LoggerInterface $logger = null)
    {
        $this->timeLimitInSeconds = $timeLimitInSeconds;
        $this->logger = $logger ?: new NullLogger();
    }

    public function onWorkerStarted(WorkerStartedEvent $event): void
    {
        $worker = $event->getWorker();

        if ($worker->isRunning()) {
            $this->endTime = microtime(true) + $this->timeLimitInSeconds;
        }
    }

    public function onWorkerStopped(WorkerStoppedEvent $event): void
    {
        $worker = $event->getWorker();

        if ($this->endTime <= microtime(true)) {
            $worker->stop();
            $this->logger->info(sprintf('Worker stopped due to time limit of %d seconds exceeded', $this->timeLimitInSeconds));
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            WorkerStartedEvent::class => 'onWorkerStarted',
            WorkerStoppedEvent::class => 'onWorkerStopped',
        ];
    }
}
