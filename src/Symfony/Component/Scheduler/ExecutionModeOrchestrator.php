<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler;

use Symfony\Component\Scheduler\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.2
 */
final class ExecutionModeOrchestrator implements ExecutionModeOrchestratorInterface
{
    public const ROUND_ROBIN = 'round_robin';
    public const DEADLINE = 'deadline';
    public const BATCH = 'batch';
    public const FIFO = 'first_in_first_out';
    public const IDLE = 'idle';
    public const NORMAL = 'normal';
    public const EXECUTION_MODES = [
        self::ROUND_ROBIN,
        self::DEADLINE,
        self::BATCH,
        self::FIFO,
        self::IDLE,
        self::NORMAL,
    ];

    private $mode;

    public function __construct(string $mode = self::FIFO)
    {
        if (!\in_array($mode, self::EXECUTION_MODES)) {
            throw new \InvalidArgumentException(sprintf('The given mode "%s" is not a valid one, allowed ones are: "%s"', $mode, implode(', ', self::EXECUTION_MODES)));
        }

        $this->mode = $mode;
    }

    /**
     * {@inheritdoc}
     */
    public function sort(array $tasks): array
    {
        if (self::FIFO !== $this->mode && !\in_array($this->mode, self::EXECUTION_MODES)) {
            throw new \InvalidArgumentException(sprintf('The given mode "%s" is not a valid one, allowed ones are: "%s"', $this->mode, implode(', ', self::EXECUTION_MODES)));
        }

        switch ($this->mode) {
            case self::NORMAL:
                return $this->sortByNice($tasks);
            case self::ROUND_ROBIN:
                return $this->sortByExecutionDuration($tasks);
            case self::DEADLINE:
                return $this->sortByDeadline($tasks);
            case self::BATCH:
                return $this->sortByBatch($tasks);
            case self::IDLE:
                return $this->idleSort($tasks);
            default:
                return $this->sortByPriority($tasks);
        }
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    private function sortByPriority(array $tasks): array
    {
        uasort($tasks, function (TaskInterface $task, TaskInterface $nextTask): bool {
            return $task->getPriority() >= 0 && $task->getPriority() < $nextTask->getPriority();
        });

        return $tasks;
    }

    /**
     * {@see https://man7.org/linux/man-pages/man7/sched.7.html#SCHED_RR}
     *
     * @param TaskInterface[] $tasks
     *
     * @return TaskInterface[]
     */
    private function sortByExecutionDuration(array $tasks) : array
    {
        uasort($tasks, function (TaskInterface $task, TaskInterface $nextTask): bool {
            return $task->getExecutionComputationTime() >= $task->getMaxDuration() && $task->getExecutionComputationTime() < $nextTask->getExecutionComputationTime();
        });

        return $tasks;
    }

    /**
     * {@see https://man7.org/linux/man-pages/man7/sched.7.html#SCHED_DEADLINE or http://ceur-ws.org/Vol-1291/ewili14_5.pdf}
     *
     * @param TaskInterface[] $tasks
     *
     * @return TaskInterface[]
     */
    private function sortByDeadline(array $tasks): array
    {
        foreach ($tasks as $task) {
            if (null === $task->getExecutionRelativeDeadline() || null === $task->getArrivalTime()) {
                continue;
            }

            $arrivalTime = $task->getArrivalTime();
            $absoluteDeadlineDate = $arrivalTime->add($task->getExecutionRelativeDeadline());

            $task->setExecutionAbsoluteDeadline($absoluteDeadlineDate->diff($arrivalTime));
        }

        uasort($tasks, function (TaskInterface $task, TaskInterface $nextTask): bool {
            $currentDate = new \DateTimeImmutable();

            return $currentDate->add($task->getExecutionAbsoluteDeadline()) < $currentDate->add($nextTask->getExecutionAbsoluteDeadline());
        });

        return $tasks;
    }

    private function sortByBatch(array $tasks): array
    {
        array_walk($tasks, function (TaskInterface $task): void {
            $priority = $task->getPriority();
            $task->setPriority(--$priority);
        });

        uasort($tasks, function (TaskInterface $task, TaskInterface $nextTask): bool {
            return $task->getPriority() < $nextTask->getPriority();
        });

        return $tasks;
    }

    private function idleSort(array $tasks): array
    {
        uasort($tasks, function (TaskInterface $task, TaskInterface $nextTask): bool {
            return $task->getPriority() <= 19 && $task->getPriority() < $nextTask->getPriority();
        });

        return $tasks;
    }

    private function sortByNice(array $tasks): array
    {
        uasort($tasks, function (TaskInterface $task, TaskInterface $nextTask): bool {
            if ($task->getPriority() > 0 || $nextTask->getPriority() > 0) {
                return false;
            }

            return $task->getNice() > $nextTask->getNice();
        });

        return $tasks;
    }
}
