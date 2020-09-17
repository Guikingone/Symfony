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

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Scheduler\Exception\InvalidArgumentException;
use Symfony\Component\Scheduler\Exception\LogicException;
use Symfony\Component\Scheduler\ExecutionModeOrchestrator;
use Symfony\Component\Scheduler\Task\TaskInterface;
use Symfony\Component\Scheduler\Task\TaskList;
use Symfony\Component\Scheduler\Task\TaskListInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.3
 */
final class FilesystemTransport implements TransportInterface
{
    private const TASK_FILENAME_MASK = '%s/%s.json';

    private $filesystem;
    private $path;
    private $orchestrator;
    private $options;
    private $serializer;

    public function __construct(string $path = null, array $options = [], SerializerInterface $serializer = null)
    {
        $this->filesystem = new Filesystem();
        $this->path = null === $path ? sys_get_temp_dir() : $path;
        $this->options = $options;
        $this->serializer = $serializer;
        $this->orchestrator = new ExecutionModeOrchestrator($options['execution_mode'] ?? ExecutionModeOrchestrator::FIFO);
    }

    /**
     * {@inheritdoc}
     */
    public function list(): TaskListInterface
    {
        $tasks = [];

        $finder = new Finder();

        $finder->files()->in($this->path)->name('*.json');
        foreach ($finder as $task) {
            $tasks[] = $this->get(strtr($task->getFilename(), ['.json' => '']));
        }

        return new TaskList($tasks);
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $taskName): TaskInterface
    {
        if (!$this->fileExist($taskName)) {
            throw new InvalidArgumentException(sprintf('The "%s" task does not exist', $taskName));
        }

        return $this->serializer->deserialize(file_get_contents(sprintf(self::TASK_FILENAME_MASK, $this->path, $taskName)), TaskInterface::class, 'json');
    }

    /**
     * {@inheritdoc}
     */
    public function create(TaskInterface $task): void
    {
        if ($this->fileExist($task->getName())) {
            throw new InvalidArgumentException(sprintf('The "%s" task has already been scheduled', $task->getName()));
        }

        $data = $this->serializer->serialize($task, 'json');
        $this->filesystem->dumpFile(sprintf(self::TASK_FILENAME_MASK, $this->path, $task->getName()), $data);
    }

    /**
     * {@inheritdoc}
     */
    public function update(string $taskName, TaskInterface $updatedTask): void
    {
        if (!$this->fileExist($taskName)) {
            throw new InvalidArgumentException(sprintf('The "%s" task does not exist', $taskName));
        }

        $this->filesystem->remove(sprintf(self::TASK_FILENAME_MASK, $this->path, $taskName));
        $this->create($updatedTask);
    }

    /**
     * {@inheritdoc}
     */
    public function pause(string $taskName): void
    {
        if (!$this->fileExist($taskName)) {
            throw new InvalidArgumentException(sprintf('The "%s" task does not exist', $taskName));
        }

        $task = $this->get($taskName);
        if ($task->getState() === TaskInterface::PAUSED) {
            throw new LogicException(sprintf('The task "%s" is already paused', $taskName));
        }

        $task->setState(TaskInterface::PAUSED);
        $this->update($taskName, $task);
    }

    /**
     * {@inheritdoc}
     */
    public function resume(string $taskName): void
    {
        if (!$this->fileExist($taskName)) {
            throw new InvalidArgumentException(sprintf('The "%s" task does not exist', $taskName));
        }

        $task = $this->get($taskName);
        if ($task->getState() === TaskInterface::ENABLED) {
            throw new LogicException(sprintf('The task "%s" is already enabled', $taskName));
        }

        $task->setState(TaskInterface::ENABLED);
        $this->update($taskName, $task);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $taskName): void
    {
        $this->filesystem->remove(sprintf(self::TASK_FILENAME_MASK, $this->path, $taskName));
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): void
    {
        $finder = new Finder();

        $finder->files()->in($this->path)->name('*.json');
        foreach ($finder as $task) {
            $this->filesystem->remove(sprintf(self::TASK_FILENAME_MASK, $this->path, strtr($task->getFilename(), ['.json' => ''])));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    private function fileExist(string $taskName): bool
    {
        return $this->filesystem->exists(sprintf(self::TASK_FILENAME_MASK, $this->path, $taskName));
    }
}
