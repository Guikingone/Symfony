<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Transport;

use Symfony\Component\Scheduler\Exception\TransportException;
use Symfony\Component\Scheduler\Task\TaskInterface;
use Symfony\Component\Scheduler\Task\TaskListInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.3
 */
final class RoundRobinTransport implements TransportInterface
{
    private const DEFAULT_OPTIONS = [
        'quantum' => 2,
    ];

    private $transports;
    private $options;
    private $sleepingTransports;

    /**
     * @param iterable|TransportInterface[] $transports
     */
    public function __construct(iterable $transports, array $options = [])
    {
        $this->transports = $transports;
        $this->sleepingTransports = new \SplObjectStorage();
        $this->options = array_replace_recursive(self::DEFAULT_OPTIONS, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $name): TaskInterface
    {
        return $this->execute(function (TransportInterface $transport) use ($name): TaskInterface {
           return $transport->get($name);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function list(): TaskListInterface
    {
        return $this->execute(function (TransportInterface $transport): TaskListInterface {
            return $transport->list();
        });
    }

    /**
     * {@inheritdoc}
     */
    public function create(TaskInterface $task): void
    {
        $this->execute(function (TransportInterface $transport) use ($task): TaskListInterface {
            $transport->create($task);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function update(string $name, TaskInterface $updatedTask): void
    {
        $this->execute(function (TransportInterface $transport) use ($name, $updatedTask): TaskListInterface {
            $transport->update($name, $updatedTask);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $name): void
    {
        $this->execute(function (TransportInterface $transport) use ($name): TaskListInterface {
            $transport->delete($name);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function pause(string $name): void
    {
        $this->execute(function (TransportInterface $transport) use ($name): TaskListInterface {
            $transport->pause($name);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function resume(string $name): void
    {
        $this->execute(function (TransportInterface $transport) use ($name): TaskListInterface {
            $transport->resume($name);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): void
    {
        $this->execute(function (TransportInterface $transport): TaskListInterface {
            $transport->clear();
        });
    }

    /**
     * {@inheritdoc}
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    private function execute(\Closure $func)
    {
        if (empty($this->transports)) {
            throw new TransportException('No transport found');
        }

        throw new TransportException('All the transports failed to execute the requested action');
    }
}
