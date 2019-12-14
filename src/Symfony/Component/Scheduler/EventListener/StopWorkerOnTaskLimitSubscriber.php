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
use Symfony\Component\Scheduler\Event\WorkerRunningEvent;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.3
 */
final class StopWorkerOnTaskLimitSubscriber implements EventSubscriberInterface
{
    private $consumedTasks = 0;
    private $maximumTasks;
    private $logger;

    public function __construct(int $maximumTasks, LoggerInterface $logger = null)
    {
        $this->maximumTasks = $maximumTasks;
        $this->logger = $logger ?: new NullLogger();
    }

    public function onWorkerRunning(WorkerRunningEvent $event): void
    {
        if (!$event->isIdle() && ++$this->consumedTasks >= $this->maximumTasks) {
            $event->getWorker()->stop();

            $this->logger->info('The worker has been stopped due to maximum tasks executed', [
                'count' => $this->consumedTasks,
            ]);
        }
    }

    /**
     * @return array<string,string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            WorkerRunningEvent::class => 'onWorkerRunning',
        ];
    }
}
