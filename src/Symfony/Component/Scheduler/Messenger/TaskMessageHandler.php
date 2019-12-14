<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Messenger;

use Cron\CronExpression;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Component\Scheduler\Worker\WorkerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.2
 */
final class TaskMessageHandler implements MessageHandlerInterface
{
    private $worker;

    public function __construct(WorkerInterface $worker)
    {
        $this->worker = $worker;
    }

    /**
     * @throws \Exception
     */
    public function __invoke(TaskMessage $message): void
    {
        $task = $message->getTask();

        if (!CronExpression::factory($task->getExpression())->isDue(new \DateTimeImmutable('now', $task->getTimezone()), $task->getTimezone())) {
            return;
        }

        while ($this->worker->isRunning()) {
            $timeout = $message->getWorkerTimeout();

            is_float($timeout) ? usleep($timeout) : sleep($timeout);
        }

        $this->worker->execute($task);
    }
}
