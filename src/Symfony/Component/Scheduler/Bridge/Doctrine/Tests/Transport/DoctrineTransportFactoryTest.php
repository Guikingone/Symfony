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

use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ConnectionRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\Bridge\Doctrine\Transport\DoctrineTransport;
use Symfony\Component\Scheduler\Bridge\Doctrine\Transport\DoctrineTransportFactory;
use Symfony\Component\Scheduler\Exception\TransportException;
use Symfony\Component\Scheduler\Transport\Dsn;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class DoctrineTransportFactoryTest extends TestCase
{
    public function testTransportCanSupport(): void
    {
        $registry = $this->createMock(ConnectionRegistry::class);

        $factory = new DoctrineTransportFactory($registry);

        static::assertFalse($factory->support('test://'));
        static::assertTrue($factory->support('doctrine://'));
    }

    public function testFactoryCannotReturnUndefinedTransport(): void
    {
        $registry = $this->createMock(ConnectionRegistry::class);
        $registry->expects(self::once())->method('getConnection')->willThrowException(new \InvalidArgumentException('Doctrine %s Connection named "%s" does not exist.'));

        $serializer = $this->createMock(SerializerInterface::class);

        $factory = new DoctrineTransportFactory($registry);

        static::expectException(TransportException::class);
        static::expectExceptionMessage('Could not find Doctrine connection from Scheduler DSN "doctrine://test".');
        $factory->createTransport(Dsn::fromString('doctrine://test'), [], $serializer);
    }

    public function testFactoryReturnTransport(): void
    {
        $connection = $this->createMock(Connection::class);

        $registry = $this->createMock(ConnectionRegistry::class);
        $registry->expects(self::once())->method('getConnection')->willReturn($connection);

        $serializer = $this->createMock(SerializerInterface::class);

        $factory = new DoctrineTransportFactory($registry);
        static::assertInstanceOf(DoctrineTransport::class, $factory->createTransport(Dsn::fromString('doctrine://default'), [], $serializer));
    }

    public function testFactoryReturnTransportWithExecutionMode(): void
    {
        $connection = $this->createMock(Connection::class);

        $registry = $this->createMock(ConnectionRegistry::class);
        $registry->expects(self::once())->method('getConnection')->willReturn($connection);

        $serializer = $this->createMock(SerializerInterface::class);

        $factory = new DoctrineTransportFactory($registry);
        static::assertInstanceOf(DoctrineTransport::class, $factory->createTransport(Dsn::fromString('doctrine://default?execution_mode=first_in_first_out'), [], $serializer));
    }

    public function testFactoryReturnTransportWithTableName(): void
    {
        $connection = $this->createMock(Connection::class);

        $registry = $this->createMock(ConnectionRegistry::class);
        $registry->expects(self::once())->method('getConnection')->willReturn($connection);

        $serializer = $this->createMock(SerializerInterface::class);

        $factory = new DoctrineTransportFactory($registry);
        static::assertInstanceOf(DoctrineTransport::class, $factory->createTransport(Dsn::fromString('doctrine://default?table_name=test'), [], $serializer));
    }
}
