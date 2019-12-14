<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\DataCollector;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpKernel\DataCollector\LateDataCollectorInterface;
use Symfony\Component\Scheduler\EventListener\TaskLoggerSubscriber;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.2
 */
final class SchedulerDataCollector extends DataCollector implements LateDataCollectorInterface
{
    private $tasks;

    public function __construct(TaskLoggerSubscriber $logger)
    {
        $this->tasks = $logger->getTasks();
    }

    /**
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, \Throwable $exception = null): void
    {
        // As data can comes from Messenger, local or remote schedulers|workers, we should collect it as late as possible.
    }

    /**
     * {@inheritdoc}
     */
    public function lateCollect(): void
    {
        $this->reset();

        $this->data['tasks'] = $this->tasks;
    }

    public function getTasks(): array
    {
        return $this->data['tasks'];
    }

    /**
     * {@inheritdoc}
     */
    public function reset(): void
    {
        $this->data = [];
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'scheduler';
    }
}
