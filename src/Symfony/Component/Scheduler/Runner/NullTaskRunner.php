<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Runner;

use Symfony\Component\Scheduler\Task\NullTask;
use Symfony\Component\Scheduler\Task\Output;
use Symfony\Component\Scheduler\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.2
 */
final class NullTaskRunner implements RunnerInterface
{
    /**
     * {@inheritdoc}
     */
    public function run(TaskInterface $task): Output
    {
        return new Output($task, null);
    }

    /**
     * {@inheritdoc}
     */
    public function support(TaskInterface $task): bool
    {
        return $task instanceof NullTask;
    }
}
