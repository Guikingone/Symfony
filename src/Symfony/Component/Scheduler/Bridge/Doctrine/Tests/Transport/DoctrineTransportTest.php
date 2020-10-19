<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Bridge\Doctrine\Tests\Transport;

use Doctrine\Common\Collections\Criteria;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\SchemaConfig;
use Doctrine\DBAL\Statement;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\Bridge\Doctrine\Transport\DoctrineTransport;
use Symfony\Component\Scheduler\Task\NullTask;
use Symfony\Component\Scheduler\Task\TaskInterface;
use Symfony\Component\Scheduler\Task\TaskListInterface;
use Symfony\Component\Scheduler\Transport\ConnectionInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class DoctrineTransportTest extends TestCase
{
    public function testTransportCanListTasks(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects(self::exactly(2))->method('deserialize')->willReturnOnConsecutiveCalls(
            new NullTask('foo'),
            new NullTask('bar')
        );

        $statement = $this->getStatementMock([
            [
                'id' => 1,
                'task_name' => 'foo',
                'body' => json_encode([
                    'body' => [
                        'expression' => '* * * * *',
                        'priority' => 1,
                        'tracked' => true,
                    ],
                    'taskInternalType' => NullTask::class,
                ]),
            ],
            [
                'id' => 2,
                'task_name' => 'bar',
                'body' => json_encode([
                    'body' => [
                        'expression' => '* * * * *',
                        'priority' => 2,
                        'tracked' => false,
                    ],
                    'taskInternalType' => NullTask::class,
                ]),
            ],
        ], true);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects(self::once())->method('select')
            ->with(self::equalTo('t.*'))
            ->willReturnSelf()
        ;
        $queryBuilder->expects(self::once())->method('from')
            ->with(self::equalTo('_symfony_scheduler_tasks'), self::equalTo('t'))
            ->willReturnSelf()
        ;
        $queryBuilder->expects(self::once())->method('orderBy')
            ->with(self::equalTo('task_name'), Criteria::ASC)
            ->willReturnSelf()
        ;
        $queryBuilder->expects(self::once())->method('getSQL')
            ->willReturn('SELECT * FROM _symfony_scheduler_tasks')
        ;

        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('createQueryBuilder')->willReturn($queryBuilder);
        $connection->expects(self::once())->method('executeQuery')
            ->with(self::equalTo('SELECT * FROM _symfony_scheduler_tasks'))
            ->willReturn($statement)
        ;

        $transport = new DoctrineTransport([
            'connection' => 'default',
            'execution_mode' => 'normal',
            'auto_setup' => true,
            'table_name' => '_symfony_scheduler_tasks',
        ], $connection, $serializer);

        $list = $transport->list();

        static::assertInstanceOf(TaskListInterface::class, $list);
        static::assertNotEmpty($list);
    }

    public function testTransportCanGetATask(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects(self::once())->method('deserialize')->willReturn($task);

        $statement = $this->getStatementMock([
            'id' => 1,
            'task_name' => 'foo',
            'body' => json_encode([
                'expression' => '* * * * *',
                'taskInternalType' => NullTask::class,
            ]),
        ]);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects(self::once())->method('select')->with(self::equalTo('t.*'))->willReturnSelf();
        $queryBuilder->expects(self::once())->method('from')
            ->with(
                self::equalTo('_symfony_scheduler_tasks'),
                self::equalTo('t')
            )
            ->willReturnSelf()
        ;
        $queryBuilder->expects(self::once())->method('where')
            ->with(self::equalTo('t.task_name = :name'))
            ->willReturnSelf()
        ;
        $queryBuilder->expects(self::once())->method('setParameter')
            ->with(
                self::equalTo(':name'),
                self::equalTo('foo'),
                self::equalTo(ParameterType::STRING)
            )
            ->willReturnSelf()
        ;
        $queryBuilder->expects(self::once())->method('getSQL')
            ->willReturn('SELECT * FROM _symfony_scheduler_tasks WHERE task_name = :name')
        ;
        $queryBuilder->expects(self::once())->method('getParameters')->willReturn([':name' => 'foo']);
        $queryBuilder->expects(self::once())->method('getParameterTypes')
            ->willReturn([':name' => ParameterType::STRING])
        ;

        $connection = $this->getDBALConnectionMock();
        $connection->expects(self::once())->method('createQueryBuilder')->willReturn($queryBuilder);
        $connection->expects(self::once())->method('executeQuery')->willReturn($statement);
        $connection->expects(self::any())->method('getDatabasePlatform');

        $transport = new DoctrineTransport([
            'connection' => 'default',
            'execution_mode' => 'normal',
            'auto_setup' => true,
            'table_name' => '_symfony_scheduler_tasks',
        ], $connection, $serializer);

        static::assertInstanceOf(TaskInterface::class, $transport->get('foo'));
    }

    public function testTransportCanCreateATask(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $task = $this->createMock(TaskInterface::class);

        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('transactional');

        $transport = new DoctrineTransport([
            'connection' => 'default',
            'execution_mode' => 'normal',
            'auto_setup' => true,
            'table_name' => '_symfony_scheduler_tasks',
        ], $connection, $serializer);

        $transport->create($task);
    }

    public function testTransportCanUpdateATask(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $task = $this->createMock(TaskInterface::class);

        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('transactional');

        $transport = new DoctrineTransport([
            'connection' => 'default',
            'execution_mode' => 'normal',
            'auto_setup' => true,
            'table_name' => '_symfony_scheduler_tasks',
        ], $connection, $serializer);

        $transport->update('foo', $task);
    }

    public function testTransportCanPauseATask(): void
    {$task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('setState')->with(self::equalTo(TaskInterface::PAUSED));

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects(self::once())->method('deserialize')->willReturn($task);

        $statement = $this->getStatementMock([
            'id' => 1,
            'task_name' => 'foo',
            'body' => json_encode([
                'expression' => '* * * * *',
                'taskInternalType' => NullTask::class,
            ]),
        ]);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects(self::once())->method('select')->with(self::equalTo('t.*'))->willReturnSelf();
        $queryBuilder->expects(self::once())->method('from')
            ->with(
                self::equalTo('_symfony_scheduler_tasks'),
                self::equalTo('t')
            )
            ->willReturnSelf()
        ;
        $queryBuilder->expects(self::once())->method('where')
            ->with(self::equalTo('t.task_name = :name'))
            ->willReturnSelf()
        ;
        $queryBuilder->expects(self::once())->method('setParameter')
            ->with(
                self::equalTo(':name'),
                self::equalTo('foo'),
                self::equalTo(ParameterType::STRING)
            )
            ->willReturnSelf()
        ;
        $queryBuilder->expects(self::once())->method('getSQL')
            ->willReturn('SELECT * FROM _symfony_scheduler_tasks WHERE task_name = :name')
        ;
        $queryBuilder->expects(self::once())->method('getParameters')->willReturn([':name' => 'foo']);
        $queryBuilder->expects(self::once())->method('getParameterTypes')
            ->willReturn([':name' => ParameterType::STRING])
        ;

        $connection = $this->getDBALConnectionMock();
        $connection->expects(self::once())->method('createQueryBuilder')->willReturn($queryBuilder);
        $connection->expects(self::once())->method('executeQuery')->willReturn($statement);
        $connection->expects(self::any())->method('getDatabasePlatform');
        $connection->expects(self::once())->method('transactional');

        $transport = new DoctrineTransport([
            'connection' => 'default',
            'execution_mode' => 'normal',
            'auto_setup' => true,
            'table_name' => '_symfony_scheduler_tasks',
        ], $connection, $serializer);

        $transport->pause('foo');
    }

    public function testTransportCanResumeATask(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('setState')->with(self::equalTo(TaskInterface::ENABLED));

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects(self::once())->method('deserialize')->willReturn($task);

        $statement = $this->getStatementMock([
            'id' => 1,
            'task_name' => 'foo',
            'body' => json_encode([
                'expression' => '* * * * *',
                'taskInternalType' => NullTask::class,
            ]),
        ]);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects(self::once())->method('select')->with(self::equalTo('t.*'))->willReturnSelf();
        $queryBuilder->expects(self::once())->method('from')
            ->with(
                self::equalTo('_symfony_scheduler_tasks'),
                self::equalTo('t')
            )
            ->willReturnSelf()
        ;
        $queryBuilder->expects(self::once())->method('where')
            ->with(self::equalTo('t.task_name = :name'))
            ->willReturnSelf()
        ;
        $queryBuilder->expects(self::once())->method('setParameter')
            ->with(
                self::equalTo(':name'),
                self::equalTo('foo'),
                self::equalTo(ParameterType::STRING)
            )
            ->willReturnSelf()
        ;
        $queryBuilder->expects(self::once())->method('getSQL')
            ->willReturn('SELECT * FROM _symfony_scheduler_tasks WHERE task_name = :name')
        ;
        $queryBuilder->expects(self::once())->method('getParameters')->willReturn([':name' => 'foo']);
        $queryBuilder->expects(self::once())->method('getParameterTypes')
            ->willReturn([':name' => ParameterType::STRING])
        ;

        $connection = $this->getDBALConnectionMock();
        $connection->expects(self::once())->method('createQueryBuilder')->willReturn($queryBuilder);
        $connection->expects(self::once())->method('executeQuery')->willReturn($statement);
        $connection->expects(self::any())->method('getDatabasePlatform');
        $connection->expects(self::once())->method('transactional');

        $transport = new DoctrineTransport([
            'connection' => 'default',
            'execution_mode' => 'normal',
            'auto_setup' => true,
            'table_name' => '_symfony_scheduler_tasks',
        ], $connection, $serializer);

        $transport->resume('foo');
    }

    public function testTransportCanDeleteATask(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('transactional')->willReturnSelf();

        $transport = new DoctrineTransport([
            'connection' => 'default',
            'execution_mode' => 'normal',
            'auto_setup' => true,
            'table_name' => '_symfony_scheduler_tasks',
        ], $connection, $serializer);

        $transport->delete('foo');
    }

    public function testTransportCanEmptyTheTaskList(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $connection = $this->createMock(Connection::class);

        $connection->expects(self::once())->method('transactional');

        $transport = new DoctrineTransport([
            'connection' => 'default',
            'execution_mode' => 'normal',
            'auto_setup' => true,
            'table_name' => '_symfony_scheduler_tasks',
        ], $connection, $serializer);

        $transport->clear();
    }

    public function testTransportCanReturnOptions(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $connection = $this->createMock(Connection::class);

        $transport = new DoctrineTransport([
            'connection' => 'default',
            'execution_mode' => 'normal',
            'auto_setup' => true,
            'table_name' => '_symfony_scheduler_tasks',
        ], $connection, $serializer);

        static::assertNotEmpty($transport->getOptions());
    }

    private function getDBALConnectionMock(): MockObject
    {
        $platform = $this->createMock(AbstractPlatform::class);
        $platform->method('getWriteLockSQL')->willReturn('FOR UPDATE');

        $configuration = $this->createMock(Configuration::class);

        $driverConnection = $this->createMock(Connection::class);
        $driverConnection->method('getDatabasePlatform')->willReturn($platform);
        $driverConnection->method('getConfiguration')->willReturn($configuration);

        $schemaManager = $this->createMock(AbstractSchemaManager::class);

        $schemaConfig = $this->createMock(SchemaConfig::class);
        $schemaConfig->method('getMaxIdentifierLength')->willReturn(63);
        $schemaConfig->method('getDefaultTableOptions')->willReturn([]);
        $schemaManager->method('createSchemaConfig')->willReturn($schemaConfig);

        $driverConnection->method('getSchemaManager')->willReturn($schemaManager);

        return $driverConnection;
    }

    private function getStatementMock($expectedResult, bool $list = false): MockObject
    {
        $statement = $this->createMock(interface_exists(Result::class) ? Result::class : Statement::class);
        if ($list && interface_exists(Result::class)) {
            $statement->expects(self::once())->method('fetchAllAssociative')->willReturn($expectedResult);
        }

        if ($list && !interface_exists(Result::class)) {
            $statement->expects(self::once())->method('fetchAll')->willReturn($expectedResult);
        }

        if (!$list && interface_exists(Result::class)) {
            $statement->expects(self::once())->method('fetchAssociative')->willReturn($expectedResult);
        }

        if (!$list && !interface_exists(Result::class)) {
            $statement->expects(self::once())->method('fetch')->willReturn($expectedResult);
        }

        return $statement;
    }
}
