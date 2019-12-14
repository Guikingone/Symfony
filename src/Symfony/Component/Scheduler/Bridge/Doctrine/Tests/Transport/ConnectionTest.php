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
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaConfig;
use Doctrine\DBAL\Schema\Table;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\Bridge\Doctrine\Transport\Connection as DoctrineConnection;
use Symfony\Component\Scheduler\Exception\InvalidArgumentException;
use Symfony\Component\Scheduler\Exception\LogicException;
use Symfony\Component\Scheduler\Exception\TransportException;
use Symfony\Component\Scheduler\Task\NullTask;
use Symfony\Component\Scheduler\Task\TaskInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ConnectionTest extends TestCase
{
    public function testConnectionCanReturnATaskList(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects(self::exactly(2))->method('deserialize')->willReturnOnConsecutiveCalls(
            new NullTask('foo'),
            new NullTask('bar')
        );

        $queryBuilder = $this->getQueryBuilderMock();
        $queryBuilder->expects(self::once())->method('orderBy')->with(self::equalTo('task_name'), Criteria::ASC);

        $driverConnection = $this->getDBALConnectionMock();
        $driverConnection->expects(self::once())->method('createQueryBuilder')->willReturn($queryBuilder);

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
                    'internalType' => NullTask::class,
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
                    'internalType' => NullTask::class,
                ]),
            ],
        ], true);

        $queryBuilder->expects(self::once())->method('getSQL')->willReturn('SELECT * FROM _symfony_scheduler_tasks');
        $queryBuilder->expects(self::never())->method('getParameterTypes');
        $driverConnection->expects(self::once())->method('executeQuery')->willReturn($statement);

        $connection = new DoctrineConnection([], $driverConnection, $serializer);
        $taskList = $connection->list();

        static::assertNotEmpty($taskList);
        static::assertInstanceOf(TaskInterface::class, $taskList->get('bar'));

        $list = $taskList->toArray(false);
        static::assertSame('foo', $list[0]->getName());
        static::assertSame('bar', $list[1]->getName());
    }

    public function testConnectionCanReturnAnEmptyTaskList(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $queryBuilder = $this->getQueryBuilderMock();
        $queryBuilder->expects(self::once())->method('orderBy')->with(self::equalTo('task_name'), Criteria::ASC);
        $queryBuilder->expects(self::once())->method('getSQL')->willReturn('SELECT * FROM _symfony_scheduler_tasks');
        $queryBuilder->expects(self::never())->method('getParameterTypes');

        $driverConnection = $this->getDBALConnectionMock();
        $driverConnection->expects(self::once())->method('createQueryBuilder')->willReturn($queryBuilder);

        $statement = $this->getStatementMock([], true);

        $driverConnection->expects(self::once())->method('executeQuery')->willReturn($statement);

        $connection = new DoctrineConnection([], $driverConnection, $serializer);
        $taskList = $connection->list();

        static::assertEmpty($taskList);
    }

    public function testConnectionCannotReturnAnInvalidTask(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $statement = $this->getStatementMock(null);

        $queryBuilder = $this->getQueryBuilderMock();
        $queryBuilder->expects(self::once())->method('getSQL')->willReturn('SELECT * FROM _symfony_scheduler_tasks WHERE task_name = "foo"');

        $driverConnection = $this->getDBALConnectionMock();
        $driverConnection->expects(self::once())->method('createQueryBuilder')->willReturn($queryBuilder);
        $driverConnection->expects(self::once())->method('executeQuery')->willReturn($statement);

        $connection = new DoctrineConnection([], $driverConnection, $serializer);

        static::expectException(TransportException::class);
        static::expectExceptionMessage('The desired task cannot be found.');
        $connection->get('foo');
    }

    public function testConnectionCanReturnASingleTask(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects(self::once())->method('deserialize')->willReturn(new NullTask('foo'));

        $statement = $this->getStatementMock([
            'id' => 1,
            'task_name' => 'foo',
            'body' => json_encode([
                'body' => [
                    'expression' => '* * * * *',
                ],
                'internal_type' => NullTask::class,
            ]),
        ]);

        $queryBuilder = $this->getQueryBuilderMock();
        $queryBuilder->expects(self::once())->method('where')->with(self::equalTo('t.task_name = ?'));
        $queryBuilder->expects(self::once())->method('getSQL')->willReturn('SELECT * FROM _symfony_scheduler_tasks WHERE task_name = "foo"');

        $driverConnection = $this->getDBALConnectionMock();
        $driverConnection->expects(self::once())->method('createQueryBuilder')->willReturn($queryBuilder);
        $driverConnection->expects(self::once())->method('executeQuery')->willReturn($statement);
        $driverConnection->expects(self::any())->method('getDatabasePlatform');

        $connection = new DoctrineConnection([], $driverConnection, $serializer);
        $task = $connection->get('foo');

        static::assertInstanceOf(TaskInterface::class, $task);
        static::assertSame('foo', $task->getName());
        static::assertSame('* * * * *', $task->getExpression());
    }

    public function testConnectionCannotInsertASingleTaskWithExistingIdentifier(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $task = $this->createMock(TaskInterface::class);

        $driverConnection = $this->getDBALConnectionMock();
        $driverConnection->expects(self::once())->method('transactional')->willThrowException(new LogicException('The task "foo" has already been scheduled!'));

        $connection = new DoctrineConnection([], $driverConnection, $serializer);

        static::expectException(TransportException::class);
        static::expectExceptionMessage('The task "foo" has already been scheduled!');
        $connection->create($task);
    }

    public function testConnectionCannotInsertASingleTaskWithDuplicatedIdentifier(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $task = $this->createMock(TaskInterface::class);

        $driverConnection = $this->getDBALConnectionMock();
        $driverConnection->expects(self::once())->method('transactional')->willThrowException(new DBALException('The given data are invalid.'));

        $connection = new DoctrineConnection([], $driverConnection, $serializer);

        static::expectException(TransportException::class);
        static::expectExceptionMessage('The given data are invalid.');
        $connection->create($task);
    }

    public function testConnectionCanInsertASingleTask(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $task = $this->createMock(TaskInterface::class);

        $driverConnection = $this->getDBALConnectionMock();
        $driverConnection->expects(self::once())->method('transactional');

        $connection = new DoctrineConnection([], $driverConnection, $serializer);
        $connection->create($task);
    }

    public function testConnectionCannotPauseASingleTaskWithInvalidIdentifier(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $queryBuilder = $this->getQueryBuilderMock();
        $queryBuilder->expects(self::once())->method('getSQL')->willReturn('UPDATE _symfony_schduer_tasks SET state = "paused" WHERE task_name = "bar"');
        $queryBuilder->expects(self::never())->method('getParameterTypes');

        $statement = $this->getStatementMock([]);

        $driverConnection = $this->getDBALConnectionMock();
        $driverConnection->expects(self::once())->method('executeQuery')->willReturn($statement);
        $driverConnection->expects(self::once())->method('createQueryBuilder')->willReturn($queryBuilder);

        $connection = new DoctrineConnection([], $driverConnection, $serializer);

        static::expectException(TransportException::class);
        static::expectExceptionMessage('The desired task cannot be found.');
        $connection->pause('bar');
    }

    public function testConnectionCanPauseASingleTask(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects(self::once())->method('serialize');
        $serializer->expects(self::once())->method('deserialize')->willReturn(new NullTask('foo'));

        $queryBuilder = $this->getQueryBuilderMock();

        $queryBuilder->expects(self::once())->method('getSQL')->willReturn('UPDATE _symfony_schduer_tasks SET state = "paused" WHERE task_name = "foo"');
        $queryBuilder->expects(self::never())->method('getParameterTypes');

        $statement = $this->getStatementMock([
            'id' => 1,
            'task_name' => 'foo',
            'body' => [
                'expression' => '* * * * *',
                'priority' => 1,
                'tracked' => true,
                'internal_type' => NullTask::class,
            ],
        ]);

        $driverConnection = $this->getDBALConnectionMock();
        $driverConnection->expects(self::once())->method('createQueryBuilder')->willReturn($queryBuilder);
        $driverConnection->expects(self::once())->method('transactional');
        $driverConnection->expects(self::once())->method('executeQuery')->willReturn($statement);

        $connection = new DoctrineConnection([], $driverConnection, $serializer);
        $connection->pause('foo');
    }

    public function testConnectionCannotResumeASingleTaskWithInvalidIdentifier(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $queryBuilder = $this->getQueryBuilderMock();
        $queryBuilder->expects(self::once())->method('getSQL')->willReturn('UPDATE _symfony_schduer_tasks SET state = "enabled" WHERE task_name = "bar"');
        $queryBuilder->expects(self::never())->method('getParameterTypes');

        $statement = $this->getStatementMock([]);

        $driverConnection = $this->getDBALConnectionMock();
        $driverConnection->expects(self::once())->method('executeQuery')->willReturn($statement);
        $driverConnection->expects(self::once())->method('createQueryBuilder')->willReturn($queryBuilder);

        $connection = new DoctrineConnection([], $driverConnection, $serializer);

        static::expectException(TransportException::class);
        static::expectExceptionMessage('The desired task cannot be found.');
        $connection->resume('foo');
    }

    public function testConnectionCanResumeASingleTask(): void
    {
        $task = new NullTask('foo');
        $task->setState(TaskInterface::PAUSED);

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects(self::once())->method('serialize');
        $serializer->expects(self::once())->method('deserialize')->willReturn($task);

        $queryBuilder = $this->getQueryBuilderMock();
        $queryBuilder->expects(self::once())->method('getSQL')->willReturn('SELECT * FROM _symfony_scheduler_tasks WHERE task_name = "foo"');

        $statement = $this->getStatementMock([
            'id' => 1,
            'task_name' => 'foo',
            'body' => [
                'expression' => '* * * * *',
                'priority' => 1,
                'tracked' => true,
                'internal_type' => NullTask::class,
            ],
        ]);

        $driverConnection = $this->getDBALConnectionMock();
        $driverConnection->expects(self::once())->method('createQueryBuilder')->willReturn($queryBuilder);
        $driverConnection->expects(self::once())->method('executeQuery')->willReturn($statement);
        $driverConnection->expects(self::once())->method('transactional');

        $connection = new DoctrineConnection([], $driverConnection, $serializer);
        $connection->resume('foo');
    }

    public function testConnectionCannotDeleteASingleTaskWithInvalidIdentifier(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $doctrineConnection = $this->getDBALConnectionMock();
        $doctrineConnection->expects(self::once())->method('transactional')->willThrowException(new InvalidArgumentException('The given identifier is invalid.'));

        $connection = new DoctrineConnection([], $doctrineConnection, $serializer);

        static::expectException(TransportException::class);
        static::expectExceptionMessage('The given identifier is invalid.');
        $connection->delete('bar');
    }

    public function testConnectionCannotDeleteASingleTaskWithValidIdentifier(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $driverConnection = $this->getDBALConnectionMock();
        $driverConnection->expects(self::once())->method('transactional');

        $connection = new DoctrineConnection([], $driverConnection, $serializer);
        $connection->delete('foo');
    }

    public function testConnectionCannotEmptyWithInvalidIdentifier(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $driverConnection = $this->getDBALConnectionMock();
        $driverConnection->expects(self::once())->method('transactional')->willThrowException(new DBALException());

        $connection = new DoctrineConnection([], $driverConnection, $serializer);

        static::expectException(TransportException::class);
        $connection->empty();
    }

    public function testConnectionCanEmptyWithValidIdentifier(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $driverConnection = $this->getDBALConnectionMock();
        $driverConnection->expects(self::once())->method('transactional');

        $connection = new DoctrineConnection([], $driverConnection, $serializer);
        $connection->empty();
    }

    public function testConnectionCannotConfigureSchemaWithExistingTable(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $driverConnection = $this->getDBALConnectionMock();
        $table = $this->createMock(Table::class);

        $column = $this->createMock(Column::class);
        $column->expects(self::never())->method('setAutoincrement');
        $column->expects(self::never())->method('setNotNull');

        $schema = $this->createMock(Schema::class);
        $schema->expects(self::once())->method('hasTable')->willReturn(true);
        $schema->expects(self::never())->method('createTable');

        $table->expects(self::never())->method('addColumn');
        $table->expects(self::never())->method('setPrimaryKey');
        $table->expects(self::never())->method('addIndex');

        $connection = new DoctrineConnection([], $driverConnection, $serializer);
        $connection->configureSchema($schema, $driverConnection);
    }

    public function testConnectionCanConfigureSchema(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $driverConnection = $this->getDBALConnectionMock();
        $table = $this->createMock(Table::class);

        $column = $this->createMock(Column::class);
        $column->expects(self::once())->method('setAutoincrement')->willReturnSelf();
        $column->expects(self::exactly(3))->method('setNotNull')->with(true);

        $schema = $this->createMock(Schema::class);
        $schema->expects(self::once())->method('hasTable')->willReturn(false);
        $schema->expects(self::once())->method('createTable')->with(self::equalTo('_symfony_scheduler_tasks'))->willReturn($table);

        $table->expects(self::exactly(3))->method('addColumn')->willReturn($column);
        $table->expects(self::once())->method('setPrimaryKey')->with(self::equalTo(['id']));
        $table->expects(self::once())->method('addIndex')->with(self::equalTo(['task_name']), '_symfony_scheduler_tasks_name');

        $connection = new DoctrineConnection([], $driverConnection, $serializer);
        $connection->configureSchema($schema, $driverConnection);
    }

    private function getDBALConnectionMock(): Connection
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

    private function getQueryBuilderMock(): QueryBuilder
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);

        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('update')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('set')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('orderBy')->willReturnSelf();
        $queryBuilder->method('setMaxResults')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('setParameters')->willReturnSelf();

        return $queryBuilder;
    }

    private function getStatementMock($expectedResult, bool $list = false)
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
