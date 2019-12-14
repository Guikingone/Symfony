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
use Symfony\Component\Scheduler\Transport\LongTailTransport;
use Symfony\Component\Scheduler\Transport\LongTailTransportFactory;
use Symfony\Component\Scheduler\Transport\TransportInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.3
 */
final class LongTailTransportFactoryTest extends TestCase
{
    public function testFactoryCanSupportTransport(): void
    {
        $factory = new LongTailTransportFactory([]);

        static::assertFalse($factory->support('test://'));
        static::assertTrue($factory->support('longtail://'));
        static::assertTrue($factory->support('lt://'));
    }

    /**
     * @dataProvider provideDsn
     */
    public function testFactoryCanCreateTransport(string $dsn): void
    {
        $serializer = $this->createMock(SerializerInterface::class);

        $factory = new LongTailTransportFactory([]);
        $transport = $factory->createTransport(Dsn::fromString($dsn), [], $serializer, new SchedulePolicyOrchestrator([]));

        static::assertInstanceOf(TransportInterface::class, $transport);
        static::assertInstanceOf(LongTailTransport::class, $transport);
    }

    public function provideDsn(): \Generator
    {
        yield ['longtail://(memory://first_in_first_out || memory://last_in_first_out)'];
        yield ['lt://(memory://first_in_first_out || memory://last_in_first_out)'];
    }
}
