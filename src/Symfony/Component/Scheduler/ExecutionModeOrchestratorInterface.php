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
interface ExecutionModeOrchestratorInterface
{
    /**
     * @param array<string,TaskInterface> $tasks
     *
     * @return array<string,TaskInterface>
     */
    public function sort(array $tasks): array;

    public function getMode(): string;
}
