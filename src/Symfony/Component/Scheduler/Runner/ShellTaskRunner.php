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

use Symfony\Component\Process\Process;
use Symfony\Component\Scheduler\Task\Output;
use Symfony\Component\Scheduler\Task\ShellTask;
use Symfony\Component\Scheduler\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.2
 */
final class ShellTaskRunner implements RunnerInterface
{
    /**
     * {@inheritdoc}
     */
    public function run(TaskInterface $task): Output
    {
        $process = new Process(
            $task->getCommand(),
            $task->getCwd(),
            $task->getEnvironmentVariables(),
            null,
            $task->getTimeout()
        );

        if ($task->mustRunInBackground()) {
            $process->run(null, $task->getEnvironmentVariables());

            return new Output($task, 'Task is running in background, output is not available');
        }

        $exitCode = $process->run(null, $task->getEnvironmentVariables());

        $output = $task->isOutput() ? trim($process->getOutput()) : null;

        return 0 === $exitCode ? new Output($task, $output) : new Output($task, $process->getErrorOutput(), Output::ERROR);
    }

    /**
     * {@inheritdoc}
     */
    public function support(TaskInterface $task): bool
    {
        return $task instanceof ShellTask;
    }
}
