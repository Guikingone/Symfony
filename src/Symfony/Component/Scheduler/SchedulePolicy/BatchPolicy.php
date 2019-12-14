<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\SchedulePolicy;

use Symfony\Component\Scheduler\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.3
 */
final class BatchPolicy implements PolicyInterface
{
    private const POLICY = 'batch';

    /**
     * {@inheritdoc}
     */
    public function sort(array $tasks): array
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

    /**
     * {@inheritdoc}
     */
    public function support(string $policy): bool
    {
        return self::POLICY === $policy;
    }
}
