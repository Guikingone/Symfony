<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\GooglePubSub\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Bridge\GooglePubSub\Transport\Connection;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
class ConnectionTest extends TestCase
{
    public function testFromInvalidDsn()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The given Google Pub Sub DSN "gpb://" is invalid.');

        Connection::fromDsn('gpb://');
    }

    public function testFromDsn()
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $this->assertEquals(
            new Connection(['endpoint' => 'https://pubsub.googleapis.com', 'topic' => 'test'], $httpClient),
            Connection::fromDsn('gpb://default/test', [], $httpClient)
        );
    }

    public function testFromDsnWithKmsKeyName()
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $this->assertEquals(
            new Connection(['endpoint' => 'https://pubsub.googleapis.com', 'topic' => 'test', 'kmsKeyName' => 'randomKey'], $httpClient),
            Connection::fromDsn('gpb://default/test?kmsKeyName=randomKey', [], $httpClient)
        );
    }
}
