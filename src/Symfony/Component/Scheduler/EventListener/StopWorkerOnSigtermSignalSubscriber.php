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

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Scheduler\Event\WorkerRunningEvent;
use Symfony\Component\Scheduler\Event\WorkerStartedEvent;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.2
 */
final class StopWorkerOnSigtermSignalSubscriber implements EventSubscriberInterface
{
    public function onWorkerStarted(WorkerStartedEvent $event): void
    {
        foreach ([SIGTERM, SIGINT, SIGQUIT, SIGKILL, SIGCHLD, SIGSTOP] as $signal) {
            pcntl_signal($signal, static function () use ($event): void {
                $event->getWorker()->stop();
            });
        }
    }

    public function onWorkerRunning(WorkerRunningEvent $event): void
    {
        pcntl_signal(SIGHUP, static function() use ($event): void {
           $event->getWorker()->restart();
        });
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        if (!\function_exists('pcntl_signal')) {
            return [];
        }

        return [
            WorkerStartedEvent::class => ['onWorkerStarted', 100],
            WorkerRunningEvent::class => ['onWorkerRunning', 100],
        ];
    }
}
