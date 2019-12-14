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

use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Scheduler\Task\MessengerTask;
use Symfony\Component\Scheduler\Task\Output;
use Symfony\Component\Scheduler\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.2
 */
final class MessengerTaskRunner implements RunnerInterface
{
    private $bus;

    public function __construct(MessageBusInterface $bus = null)
    {
        $this->bus = $bus;
    }

    /**
     * {@inheritdoc}
     */
    public function run(TaskInterface $task): Output
    {
        try {
            if (null === $this->bus) {
                return new Output($task, 'The task cannot be handled as the bus is not defined', Output::ERROR);
            }

            $this->bus->dispatch($task->getMessage());

            return new Output($task, null);
        } catch (\Throwable $throwable) {
            return new Output($task, $throwable->getMessage(), Output::ERROR);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function support(TaskInterface $task): bool
    {
        return $task instanceof MessengerTask;
    }
}
