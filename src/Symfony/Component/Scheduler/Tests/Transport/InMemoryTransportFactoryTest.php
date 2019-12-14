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
use Symfony\Component\Scheduler\Transport\Dsn;
use Symfony\Component\Scheduler\Transport\InMemoryTransport;
use Symfony\Component\Scheduler\Transport\InMemoryTransportFactory;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class InMemoryTransportFactoryTest extends TestCase
{
    public function testTransportCanSupport(): void
    {
        $factory = new InMemoryTransportFactory();

        static::assertFalse($factory->support('test://'));
        static::assertTrue($factory->support('memory://'));
    }

    /**
     * @dataProvider provideDsn
     */
    public function testFactoryReturnTransport(string $dsn): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $factory = new InMemoryTransportFactory();

        static::assertInstanceOf(InMemoryTransport::class, $factory->createTransport(Dsn::fromString($dsn), [], $serializer));
    }

    public function provideDsn(): \Generator
    {
        yield [
            'memory://batch',
            'memory://deadline',
            'memory://first_in_first_out',
            'memory://normal',
            'memory://normal?nice=10'
        ];
    }
}
