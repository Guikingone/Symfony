<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\SchedulePolicy\SchedulePolicyOrchestrator;
use Symfony\Component\Scheduler\Transport\InMemoryTransport;
use Symfony\Component\Scheduler\Transport\InMemoryTransportFactory;
use Symfony\Component\Scheduler\Transport\TransportFactory;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TransportFactoryTest extends TestCase
{
    public function testInMemoryTransportCanBeCreated(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $factory = new TransportFactory([new InMemoryTransportFactory()]);

        $transport = $factory->createTransport('memory://first_in_first_out', [], $serializer, new SchedulePolicyOrchestrator([]));
        static::assertInstanceOf(InMemoryTransport::class, $transport);
    }

    public function testRedisTransportCannotBeCreated(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $factory = new TransportFactory([]);

        static::expectException(\InvalidArgumentException::class);
        static::expectExceptionMessage('No transport supports the given Scheduler DSN "redis://". Run "composer require symfony/redis-scheduler" to install Redis transport.');
        $factory->createTransport('redis://', [], $serializer, new SchedulePolicyOrchestrator([]));
    }

    public function testDoctrineTransportCannotBeCreated(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $factory = new TransportFactory([]);

        static::expectException(\InvalidArgumentException::class);
        static::expectExceptionMessage('No transport supports the given Scheduler DSN "doctrine://". Run "composer require symfony/doctrine-scheduler" to install Doctrine transport.');
        $factory->createTransport('doctrine://', [], $serializer, new SchedulePolicyOrchestrator([]));
    }
}
