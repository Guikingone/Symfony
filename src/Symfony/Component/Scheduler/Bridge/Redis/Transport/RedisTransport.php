<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Bridge\Redis\Transport;

use Symfony\Component\Scheduler\Task\TaskInterface;
use Symfony\Component\Scheduler\Task\TaskListInterface;
use Symfony\Component\Scheduler\Transport\TransportInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.3
 */
final class RedisTransport implements TransportInterface
{
    private $connection;
    private $options;

    public function __construct(array $options, SerializerInterface $serializer)
    {
        $this->connection = new Connection($options, $serializer);
        $this->options = $options;
    }

    /**
     * {@inheritdoc}
     */
    public function list(): TaskListInterface
    {
        return $this->connection->list();
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $taskName): TaskInterface
    {
        return $this->connection->get($taskName);
    }

    /**
     * {@inheritdoc}
     */
    public function create(TaskInterface $task): void
    {
        $this->connection->create($task);
    }

    /**
     * {@inheritdoc}
     */
    public function update(string $taskName, TaskInterface $updatedTask): void
    {
        $this->connection->update($taskName, $updatedTask);
    }

    /**
     * {@inheritdoc}
     */
    public function pause(string $taskName): void
    {
        $this->connection->pause($taskName);
    }

    /**
     * {@inheritdoc}
     */
    public function resume(string $taskName): void
    {
        $this->connection->resume($taskName);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $taskName): void
    {
        $this->connection->delete($taskName);
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): void
    {
        $this->connection->empty();
    }

    /**
     * {@inheritdoc}
     */
    public function getOptions(): array
    {
        return $this->options;
    }
}
