<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Worker;

use Symfony\Component\Scheduler\Event\TaskExecutedEvent;
use Symfony\Component\Scheduler\Event\TaskExecutingEvent;
use Symfony\Component\Scheduler\Event\TaskFailedEvent;
use Symfony\Component\Scheduler\Event\WorkerStartedEvent;
use Symfony\Component\Scheduler\Event\WorkerStoppedEvent;
use Symfony\Component\Scheduler\Exception\UndefinedRunnerException;
use Symfony\Component\Scheduler\Task\FailedTask;
use Symfony\Component\Scheduler\Task\Output;
use Symfony\Component\Scheduler\Task\TaskInterface;
use Symfony\Component\Scheduler\Task\TaskListInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.3
 */
interface WorkerInterface
{
    /**
     * Execute the given task, if the task cannot be executed, the worker SHOULD exit.
     *
     * An exception can be throw during the execution of the task, if so, it SHOULD be handled.
     *
     * A worker COULD dispatch the following events:
     *  - {@see WorkerStartedEvent}: Contain the worker instance BEFORE executing the task.
     *  - {@see TaskExecutingEvent}: Contain the task to executed BEFORE executing the task.
     *  - {@see TaskExecutedEvent}:  Contain the task to executed AFTER executing the task and its output (if defined).
     *  - {@see TaskFailedEvent}:    Contain the task that failed {@see FailedTask}.
     *  - {@see WorkerOutputEvent}:  Contain the worker instance, the task and the {@see Output} after the execution.
     *  - {@see WorkerStoppedEvent}: Contain the worker instance AFTER executing the task.
     *
     * @throws UndefinedRunnerException If no runner capable of running the tasks is found.
     */
    public function execute(array $options = [], TaskInterface ...$tasks): void;

    public function stop(): void;

    public function restart(): void;

    public function isRunning(): bool;

    /**
     * Return a list which contain every task that has fail during execution.
     *
     * Every task in this list can also be retrieved independently thanks to {@see TaskFailedEvent}.
     *
     * @return TaskListInterface
     */
    public function getFailedTasks(): TaskListInterface;
}
