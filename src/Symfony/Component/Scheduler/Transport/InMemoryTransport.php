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

use Symfony\Component\Scheduler\Exception\InvalidArgumentException;
use Symfony\Component\Scheduler\Exception\LogicException;
use Symfony\Component\Scheduler\SchedulePolicy\SchedulePolicyOrchestratorInterface;
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

    public function __construct(array $options = [], SchedulePolicyOrchestratorInterface $schedulePolicyOrchestrator = null)
    {
        $this->options = array_replace_recursive(self::DEFAULT_OPTIONS, $options);
        $this->orchestrator = $schedulePolicyOrchestrator;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $taskName): TaskInterface
    {
        return $this->list()->get($taskName);
    }

    /**
     * {@inheritdoc}
     */
    public function list(): TaskListInterface
    {
        return new TaskList($this->tasks);
    }

    /**
     * {@inheritdoc}
     */
    public function create(TaskInterface $task): void
    {
        if (\array_key_exists($task->getName(), $this->tasks)) {
            return;
        }

        if (isset($this->options['nice'])) {
            $task->setNice($this->options['nice']);
        }

        $this->tasks[$task->getName()] = $task;
        $this->tasks = null !== $this->orchestrator ? $this->orchestrator->sort($this->options['execution_mode'], $this->tasks) : $this->tasks;
    }

    /**
     * {@inheritdoc}
     */
    public function update(string $taskName, TaskInterface $updatedTask): void
    {
        $this->list()->offsetSet($taskName, $updatedTask);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $taskName): void
    {
        unset($this->tasks[$taskName]);
    }

    /**
     * {@inheritdoc}
     */
    public function pause(string $taskName): void
    {
        $task = $this->list()->get($taskName);
        if (!$task instanceof TaskInterface) {
            throw new InvalidArgumentException(sprintf('The task "%s" does not exist', $taskName));
        }

        if (TaskInterface::PAUSED === $task->getState()) {
            throw new LogicException(sprintf('The task "%s" is already paused', $task->getName()));
        }

        $task->setState(TaskInterface::PAUSED);
        $this->update($taskName, $task);
    }

    /**
     * {@inheritdoc}
     */
    public function resume(string $taskName): void
    {
        $task = $this->list()->get($taskName);
        if (!$task instanceof TaskInterface || TaskInterface::ENABLED === $task->getState()) {
            return;
        }

        $task->setState(TaskInterface::ENABLED);
        $this->update($taskName, $task);
    }

    /**
     * {@inheritdoc}
     */
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
