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
interface SchedulePolicyOrchestratorInterface
{
    /**
     * @param TaskInterface[] $tasks
     *
     * @return TaskInterface[]
     */
    public function sort(string $policy, array $tasks): array;
}
