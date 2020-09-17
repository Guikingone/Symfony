<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Transport;

use Symfony\Component\Scheduler\Exception\AlreadyScheduledTaskException;
use Symfony\Component\Scheduler\ExecutionModeOrchestrator;
use Symfony\Component\Scheduler\Task\TaskInterface;
use Symfony\Component\Scheduler\Task\TaskList;
use Symfony\Component\Scheduler\Task\TaskListInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.3
 */
final class InMemoryTransport implements TransportInterface
{
    private const DEFAULT_OPTIONS = [
        'execution_mode' => 'first_in_first_out',
    ];

    private $options;
    private $tasks = [];
    private $orchestrator;

    public function __construct(array $options = [])
    {
        $this->options = $options;
        $this->orchestrator = new ExecutionModeOrchestrator($options['execution_mode'] ?? self::DEFAULT_OPTIONS['execution_mode']);
    }

    public function get(string $taskName): TaskInterface
    {
        return $this->list()->get($taskName);
    }

    public function list(): TaskListInterface
    {
        return new TaskList($this->tasks);
    }

    public function create(TaskInterface $task): void
    {
        if (isset($this->tasks[$task->getName()])) {
            throw new AlreadyScheduledTaskException(sprintf('The following task "%s" has already been scheduled!', $task->getName()));
        }

        if (isset($this->options['nice'])) {
            $task->setNice($this->options['nice']);
        }

        $this->tasks[$task->getName()] = $task;
        $this->tasks = $this->orchestrator->sort($this->tasks);
    }

    public function update(string $taskName, TaskInterface $updatedTask): void
    {
        $this->list()->offsetSet($taskName, $updatedTask);
    }

    public function delete(string $taskName): void
    {
        unset($this->tasks[$taskName]);
    }

    public function pause(string $taskName): void
    {
        $task = $this->list()->get($taskName);
        if (!$task instanceof TaskInterface || TaskInterface::PAUSED === $task->getState()) {
            return;
        }

        $task->setState(TaskInterface::PAUSED);
        $this->update($taskName, $task);
    }

    public function resume(string $taskName): void
    {
        $task = $this->list()->get($taskName);
        if (!$task instanceof TaskInterface || TaskInterface::ENABLED === $task->getState()) {
            return;
        }

        $task->setState(TaskInterface::ENABLED);
        $this->update($taskName, $task);
    }

    public function clear(): void
    {
        $this->tasks = [];
    }

    /**
     * {@inheritdoc}
     */
    public function getOptions(): array
    {
        return $this->options;
    }
}
