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
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Scheduler\Transport\Dsn;
use Symfony\Component\Scheduler\Transport\FilesystemTransport;
use Symfony\Component\Scheduler\Transport\FilesystemTransportFactory;
use Symfony\Component\Scheduler\Transport\TransportInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class FilesystemTransportFactoryTest extends TestCase
{
    public function testFactoryCanSupportTransport(): void
    {
        $filesystem = $this->createMock(Filesystem::class);

        $factory = new FilesystemTransportFactory($filesystem);

        static::assertFalse($factory->support('test://'));
        static::assertTrue($factory->support('fs://'));
        static::assertTrue($factory->support('file://'));
        static::assertTrue($factory->support('filesystem://'));
    }

    public function testFactoryCanCreateTransport(): void
    {
        $filesystem = $this->createMock(Filesystem::class);
        $serializer = $this->createMock(SerializerInterface::class);

        $factory = new FilesystemTransportFactory($filesystem);
        $transport = $factory->createTransport(Dsn::fromString('fs://first_in_first_out'), [], $serializer);

        static::assertInstanceOf(TransportInterface::class, $transport);
        static::assertInstanceOf(FilesystemTransport::class, $transport);
    }

    public function testFactoryCanCreateTransportWithSpecificPath(): void
    {
        $filesystem = $this->createMock(Filesystem::class);
        $serializer = $this->createMock(SerializerInterface::class);

        $factory = new FilesystemTransportFactory($filesystem);
        $transport = $factory->createTransport(Dsn::fromString('fs://first_in_first_out?path=/srv/app'), [], $serializer);

        static::assertInstanceOf(TransportInterface::class, $transport);
        static::assertInstanceOf(FilesystemTransport::class, $transport);
    }
}
