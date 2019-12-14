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

use Symfony\Component\Scheduler\Task\CallbackTask;
use Symfony\Component\Scheduler\Task\Output;
use Symfony\Component\Scheduler\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.2
 */
final class CallbackTaskRunner implements RunnerInterface
{
    /**
     * {@inheritdoc}
     */
    public function run(TaskInterface $task): Output
    {
        $output = call_user_func_array($task->getCallback(), $task->getArguments());

        return false === $output
            ? new Output($task, null, Output::ERROR)
            : new Output($task, trim($output))
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function support(TaskInterface $task): bool
    {
        return $task instanceof CallbackTask;
    }
}
