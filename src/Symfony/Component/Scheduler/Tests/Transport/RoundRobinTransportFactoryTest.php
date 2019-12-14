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
use Symfony\Component\Scheduler\Transport\RoundRobinTransport;
use Symfony\Component\Scheduler\Transport\RoundRobinTransportFactory;
use Symfony\Component\Scheduler\Transport\TransportInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class RoundRobinTransportFactoryTest extends TestCase
{
    public function testFactoryCanSupportTransport(): void
    {
        $factory = new RoundRobinTransportFactory([]);

        static::assertFalse($factory->support('test://'));
        static::assertTrue($factory->support('roundrobin://'));
        static::assertTrue($factory->support('rr://'));
    }

    /**
     * @dataProvider provideDsn
     */
    public function testFactoryCanCreateTransport(string $dsn): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $factory = new RoundRobinTransportFactory([]);
        $transport = $factory->createTransport(Dsn::fromString($dsn), [], $serializer, new SchedulePolicyOrchestrator([]));

        static::assertInstanceOf(TransportInterface::class, $transport);
        static::assertInstanceOf(RoundRobinTransport::class, $transport);
    }

    public function provideDsn(): \Generator
    {
        yield ['roundrobin://(memory://first_in_first_out && memory://last_in_first_out)'];
        yield ['rr://(memory://first_in_first_out && memory://last_in_first_out)'];
    }
}
