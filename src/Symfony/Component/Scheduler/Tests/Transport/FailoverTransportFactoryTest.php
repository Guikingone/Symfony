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
use Symfony\Component\Scheduler\Transport\Dsn;
use Symfony\Component\Scheduler\Transport\FailoverTransport;
use Symfony\Component\Scheduler\Transport\FailoverTransportFactory;
use Symfony\Component\Scheduler\Transport\TransportInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class FailoverTransportFactoryTest extends TestCase
{
    public function testFactoryCanSupportTransport(): void
    {
        $factory = new FailoverTransportFactory([]);

        static::assertFalse($factory->support('test://'));
        static::assertTrue($factory->support('failover://'));
        static::assertTrue($factory->support('fo://'));
    }

    /**
     * @dataProvider provideDsn
     */
    public function testFactoryCanCreateTransport(string $dsn): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $factory = new FailoverTransportFactory([]);
        $transport = $factory->createTransport(Dsn::fromString($dsn), [], $serializer, new SchedulePolicyOrchestrator([]));

        static::assertInstanceOf(TransportInterface::class, $transport);
        static::assertInstanceOf(FailoverTransport::class, $transport);
    }

    /**
     * @dataProvider provideDsnWithOptions
     */
    public function testFactoryCanCreateTransportWithSpecificMode(string $dsn): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $factory = new FailoverTransportFactory([]);
        $transport = $factory->createTransport(Dsn::fromString($dsn), [], $serializer, new SchedulePolicyOrchestrator([]));

        static::assertInstanceOf(TransportInterface::class, $transport);
        static::assertInstanceOf(FailoverTransport::class, $transport);
        static::assertArrayHasKey('mode', $transport->getOptions());
        static::assertSame('normal', $transport->getOptions()['mode']);
    }

    public function provideDsn(): \Generator
    {
        yield ['failover://(memory://first_in_first_out || memory://last_in_first_out)'];
        yield ['fo://(memory://first_in_first_out || memory://last_in_first_out)'];
    }

    public function provideDsnWithOptions(): \Generator
    {
        yield ['failover://(memory://first_in_first_out || memory://last_in_first_out)?mode=normal'];
        yield ['fo://(memory://first_in_first_out || memory://last_in_first_out)?mode=normal'];
    }
}
