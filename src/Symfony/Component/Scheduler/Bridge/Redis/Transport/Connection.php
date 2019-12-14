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

use Symfony\Component\Scheduler\Exception\InvalidArgumentException;
use Symfony\Component\Scheduler\Exception\TransportException;
use Symfony\Component\Scheduler\Task\TaskInterface;
use Symfony\Component\Scheduler\Task\TaskList;
use Symfony\Component\Scheduler\Task\TaskListInterface;
use Symfony\Component\Scheduler\Transport\ConnectionInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.2
 */
final class Connection implements ConnectionInterface
{
    private const DEFAULT_OPTIONS = [
        'host'    => '127.0.0.1',
        'port'    => 6379,
        'timeout' => 30,
        'dbindex' => 0,
        'list'    => '_symfony_scheduler_tasks',
    ];

    private $connection;
    private $dbIndex;
    private $list;
    private $serializer;

    public function __construct(array $options, SerializerInterface $serializer, ?\Redis $redis = null)
    {
        $this->connection = $redis ?? new \Redis();

        $this->connection->connect(
            $options['host'] ?? self::DEFAULT_OPTIONS['host'],
            $options['port'] ?? self::DEFAULT_OPTIONS['port'],
            $options['timeout'] ?? self::DEFAULT_OPTIONS['timeout']
        );

        if (0 !== strpos($this->list = $options['list'] ?? self::DEFAULT_OPTIONS['list'], '_')) {
            throw new InvalidArgumentException('The list name must start with an underscore');
        }

        if (null !== $options['auth'] && !$this->connection->auth($options['auth'])) {
            throw new InvalidArgumentException(sprintf('Redis connection failed: "%s".', $redis->getLastError()));
        }

        if (($this->dbIndex = $options['dbindex'] ?? self::DEFAULT_OPTIONS['dbindex']) && !$this->connection->select($this->dbIndex)) {
            throw new InvalidArgumentException(sprintf('Redis connection failed: "%s".', $redis->getLastError()));
        }

        $this->serializer = $serializer;
    }

    /**
     * {@inheritdoc}
     */
    public function list(): TaskListInterface
    {
        $taskList = new TaskList();
        $listLength = $this->connection->hLen($this->list);
        if (false === $listLength) {
            throw new TransportException('The list is not initialized');
        }

        if (0 === $listLength) {
            return $taskList;
        }

        $keys = $this->connection->hKeys($this->list);
        foreach ($keys as $key) {
            $taskList->add($this->get($key));
        }

        return $taskList;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $taskName): TaskInterface
    {
        if (!$this->connection->hExists($this->list, $taskName)) {
            throw new TransportException(sprintf('The task "%s" does not exist', $taskName));
        }

        $task = $this->connection->hGet($this->list, $taskName);

        return $this->serializer->deserialize($task, TaskInterface::class, 'json');
    }

    /**
     * {@inheritdoc}
     */
    public function create(TaskInterface $task): void
    {
        if ($this->connection->hExists($this->list, $task->getName())) {
            throw new TransportException(sprintf('The task "%s" has already been scheduled!', $task->getName()));
        }

        $data = $this->serializer->serialize($task, 'json');
        $this->connection->hSetNx($this->list, $task->getName(), $data);
    }

    /**
     * {@inheritdoc}
     */
    public function update(string $taskName, TaskInterface $updatedTask): void
    {
        if (!$this->connection->hExists($this->list, $taskName)) {
            throw new TransportException(sprintf('The task "%s" cannot be updated as it does not exist', $taskName ));
        }

        $body = $this->serializer->serialize($updatedTask, 'json');
        if (false === $this->connection->hSet($this->list, $taskName, $body)) {
            throw new TransportException(sprintf('The task "%s" cannot be updated, error: %s', $taskName, $this->connection->getLastError()));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function pause(string $taskName): void
    {
        $task = $this->get($taskName);
        if ($task->getState() === TaskInterface::PAUSED) {
            throw new TransportException(sprintf('The task "%s" is already paused', $taskName));
        }

        $task->setState(TaskInterface::PAUSED);

        try {
            $this->update($taskName, $task);
        } catch (\Throwable $throwable) {
            throw new TransportException(sprintf('The task "%s" cannot be paused', $taskName));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function resume(string $taskName): void
    {
        $task = $this->get($taskName);
        if ($task->getState() === TaskInterface::ENABLED) {
            throw new TransportException(sprintf('The task "%s" is already enabled', $taskName));
        }

        $task->setState(TaskInterface::ENABLED);

        try {
            $this->update($taskName, $task);
        } catch (\Throwable $throwable) {
            throw new TransportException(sprintf('The task "%s" cannot be enabled', $taskName));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $taskName): void
    {
        if (0 === $this->connection->hDel($this->list, $taskName)) {
            throw new TransportException(sprintf('The task "%s" cannot be deleted as it does not exist', $taskName));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function empty(): void
    {
        $keys = $this->connection->hKeys($this->list);

        if (!$this->connection->hDel($this->list, ...$keys)) {
            throw new TransportException('The list cannot be emptied');
        }
    }

    public function clean(): void
    {
        $this->connection->unlink($this->list);
    }
}
