<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Task;

use Symfony\Component\Stopwatch\Stopwatch;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.3
 */
final class TaskExecutionTracker implements TaskExecutionTrackerInterface
{
    private $watch;

    public function __construct(Stopwatch $watch)
    {
        $this->watch = $watch;
    }

    /**
     * {@inheritdoc}
     */
    public function startTracking(TaskInterface $task): void
    {
        if (!$task->isTracked()) {
            return;
        }

        $this->watch->start(sprintf('task_execution.%s', $task->getName()));
    }

    /**
     * {@inheritdoc}
     */
    public function endTracking(TaskInterface $task): void
    {
        if (!$task->isTracked()) {
            return;
        }

        if (!$this->watch->isStarted(sprintf('task_execution.%s', $task->getName()))) {
            return;
        }

        $event = $this->watch->stop(sprintf('task_execution.%s', $task->getName()));
        $task->setExecutionComputationTime($event->getDuration());
    }
}
