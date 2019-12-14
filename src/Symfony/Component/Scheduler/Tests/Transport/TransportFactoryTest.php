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

        $memoryTransport = new InMemoryTransportFactory();

        $factory = new TransportFactory([$memoryTransport]);

        $transport = $factory->createTransport('memory://first_in_first_out', [], $serializer);
        static::assertInstanceOf(InMemoryTransport::class, $transport);
    }
}
