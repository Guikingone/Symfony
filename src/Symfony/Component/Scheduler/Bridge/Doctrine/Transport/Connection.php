<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Bridge\Doctrine\Transport;

use Doctrine\Common\Collections\Criteria;
use Doctrine\DBAL\Connection as DBALConnection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Synchronizer\SingleDatabaseSynchronizer;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\Scheduler\Exception\InvalidArgumentException;
use Symfony\Component\Scheduler\Exception\LogicException;
use Symfony\Component\Scheduler\Exception\TransportException;
use Symfony\Component\Scheduler\ExecutionModeOrchestrator;
use Symfony\Component\Scheduler\Task\AbstractTask;
use Symfony\Component\Scheduler\Task\TaskInterface;
use Symfony\Component\Scheduler\Task\TaskList;
use Symfony\Component\Scheduler\Task\TaskListInterface;
use Symfony\Component\Scheduler\Transport\ConnectionInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.3
 */
final class Connection implements ConnectionInterface
{
    private const DEFAULT_OPTIONS = [
        'table_name' => '_symfony_scheduler_tasks',
        'auto_setup' => true,
    ];

    private $autoSetup;
    private $configuration;
    private $driverConnection;
    private $schemaSynchronizer;
    private $orchestrator;
    private $serializer;

    public function __construct(array $configuration, DBALConnection $driverConnection, SerializerInterface $serializer)
    {
        $this->configuration = array_replace_recursive(static::DEFAULT_OPTIONS, $configuration);
        $this->driverConnection = $driverConnection;
        $this->schemaSynchronizer = $schemaSynchronizer ?? new SingleDatabaseSynchronizer($this->driverConnection);
        $this->autoSetup = $this->configuration['auto_setup'];
        $this->serializer = $serializer;
        $this->orchestrator = new ExecutionModeOrchestrator($configuration['execution_mode'] ?? ExecutionModeOrchestrator::FIFO);
    }

    /**
     * {@inheritdoc}
     */
    public function list(): TaskListInterface
    {
        $taskList = new TaskList();

        try {
            $statement = $this->executeQuery($this->createQueryBuilder()->orderBy('task_name', Criteria::ASC)->getSQL());
            $tasks = $statement instanceof Result ? $statement->fetchAllAssociative() : $statement->fetchAll();
            if (empty($tasks)) {
                return $taskList;
            }

            foreach ($tasks as $task) {
                $taskList->add($this->serializer->deserialize($task['body'], TaskInterface::class, 'json'));
            }

            return $taskList;
        } catch (\Throwable $throwable) {
            throw new TransportException($throwable->getMessage());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $taskName): TaskInterface
    {
        try {
            $queryBuilder = $this->createQueryBuilder()->where('t.task_name = ?');

            $statement = $this->executeQuery(
                $queryBuilder->getSQL(). ' ' .$this->driverConnection->getDatabasePlatform()->getReadLockSQL(),
                [$taskName],
                [Types::STRING]
            );
            $data = $statement instanceof Result ? $statement->fetchAssociative() : $statement->fetch();
            if (empty($data) || false === $data) {
                throw new LogicException('The desired task cannot be found.');
            }

            return $this->serializer->deserialize($data['body'], TaskInterface::class, 'json');
        } catch (\Throwable $throwable) {
            throw new TransportException($throwable->getMessage());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function create(TaskInterface $task): void
    {
        try {
            $this->driverConnection->transactional(function (DBALConnection $connection) use ($task): void {
                $existingTaskQuery = $this->createQueryBuilder()
                    ->select('COUNT(t.id) as task_count')
                    ->where('t.task_name = :name')
                    ->setParameter(':name', $task->getName())
                    ->getSQL()
                ;

                $existingTasks = $connection->executeQuery($existingTaskQuery, ['name' => $task->getName()], [Types::STRING])->fetch();
                if ('0' !== $existingTasks['task_count']) {
                    throw new LogicException(sprintf('The task "%s" has already been scheduled!', $task->getName()));
                }

                $affectedRows = $connection->insert($this->configuration['table_name'], [
                    'task_name' => $task->getName(),
                    'body' => $this->serializer->serialize($task, 'json'),
                ], [
                    'task_name' => Types::STRING,
                    'body' => Types::TEXT,
                ]);

                if (1 !== $affectedRows) {
                    throw new DBALException('The given data are invalid.');
                }
            });
        } catch (\Throwable $throwable) {
            throw new TransportException($throwable->getMessage());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function update(string $taskName, TaskInterface $updatedTask): void
    {
        $data = $this->serializer->serialize($updatedTask, 'json');

        $this->prepareUpdate($taskName, [
            'task_name' => $updatedTask->getName(),
            'body' => $data,
        ], [
            Types::STRING,
            Types::TEXT,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function pause(string $taskName): void
    {
        try {
            $task = $this->get($taskName);
            if ($task->getState() === TaskInterface::PAUSED) {
                throw new LogicException(sprintf('The task "%s" is already paused', $taskName));
            }

            $task->setState(AbstractTask::PAUSED);
            $this->update($taskName, $task);
        } catch (\Throwable $throwable) {
            throw new TransportException($throwable->getMessage());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function resume(string $taskName): void
    {
        try {
            $task = $this->get($taskName);
            if ($task->getState() === TaskInterface::ENABLED) {
                throw new LogicException(sprintf('The task "%s" is already enabled', $taskName));
            }

            $task->setState(AbstractTask::ENABLED);
            $this->update($taskName, $task);
        } catch (\Throwable $throwable) {
            throw new TransportException($throwable->getMessage());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $taskName): void
    {
        try {
            $this->driverConnection->transactional(function (DBALConnection $connection) use ($taskName): void {
                $affectedRows = $connection->delete($this->configuration['table_name'],
                    ['task_name' => $taskName],
                    ['task_name' => Types::STRING]
                );

                if (1 !== $affectedRows) {
                    throw new InvalidArgumentException('The given identifier is invalid.');
                }
            });
        } catch (\Throwable $exception) {
            throw new TransportException($exception->getMessage());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function empty(): void
    {
        try {
            $this->driverConnection->transactional(function (DBALConnection $connection): void {
                $connection->exec(sprintf('DELETE FROM %s', $this->configuration['table_name']));
            });
        } catch (\Throwable $throwable) {
            throw new TransportException($throwable->getMessage());
        }
    }

    public function setup(): void
    {
        $configuration = $this->driverConnection->getConfiguration();
        $assetFilter = $configuration->getSchemaAssetsFilter();
        $configuration->setSchemaAssetsFilter(null);
        $this->schemaSynchronizer->updateSchema($this->getSchema(), true);
        $configuration->setSchemaAssetsFilter($assetFilter);

        $this->autoSetup = false;
    }

    public function configureSchema(Schema $schema, DbalConnection $connection): void
    {
        if ($connection !== $this->driverConnection || $schema->hasTable($this->configuration['table_name'])) {
            return;
        }

        $this->addTableToSchema($schema);
    }

    private function prepareUpdate(string $taskName, array $data, array $identifiers = []): void
    {
        try {
            $this->driverConnection->transactional(function (DBALConnection $connection) use ($taskName, $data, $identifiers): void {
                $affectedRows = $connection->update($this->configuration['table_name'], $data, ['task_name' => $taskName], $identifiers ?? [
                    'task_name' => Types::STRING,
                    'body' => Types::ARRAY,
                ]);

                if (1 !== $affectedRows) {
                    throw new DBALException('The given task cannot be updated as the identifier or the body is invalid');
                }
            });
        } catch (\Throwable $throwable) {
            throw new TransportException($throwable->getMessage());
        }
    }

    private function createQueryBuilder(): QueryBuilder
    {
        return $this->driverConnection->createQueryBuilder()
            ->select('t.*')
            ->from($this->configuration['table_name'], 't')
        ;
    }

    private function executeQuery(string $sql, array $parameters = [], array $types = [])
    {
        try {
            $stmt = $this->driverConnection->executeQuery($sql, $parameters, $types);
        } catch (\Throwable $throwable) {
            if ($this->driverConnection->isTransactionActive()) {
                throw $throwable;
            }

            if ($this->autoSetup) {
                $this->setup();
            }

            $stmt = $this->driverConnection->executeQuery($sql, $parameters, $types);
        }

        return $stmt;
    }

    private function getSchema(): Schema
    {
        $schema = new Schema([], [], $this->driverConnection->getSchemaManager()->createSchemaConfig());
        $this->addTableToSchema($schema);

        return $schema;
    }

    private function addTableToSchema(Schema $schema): void
    {
        $table = $schema->createTable($this->configuration['table_name']);
        $table->addColumn('id', Types::BIGINT)
            ->setAutoincrement(true)
            ->setNotnull(true)
        ;
        $table->addColumn('task_name', Types::STRING)
            ->setNotnull(true)
        ;
        $table->addColumn('body', Types::TEXT)
            ->setNotnull(true)
        ;

        $table->setPrimaryKey(['id']);
        $table->addIndex(['task_name'], '_symfony_scheduler_tasks_name');
    }
}
