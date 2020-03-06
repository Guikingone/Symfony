<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\GooglePubSub\Transport;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Messenger\Exception\InvalidArgumentException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class Connection
{
    private const DEFAULT_OPTIONS = [
        'access_token' => null,
        'api_key' => null,
        'endpoint' => 'https://pubsub.googleapis.com',
        'topic' => null,
    ];

    private $configuration;
    private $client;

    public function __construct(array $configuration, HttpClientInterface $client = null)
    {
        $this->configuration = array_replace_recursive(self::DEFAULT_OPTIONS, $configuration);
        $this->client = $client ?? HttpClient::create();
    }

    public static function fromDsn(string $dsn, array $options = [], HttpClientInterface $client = null): self
    {
        if (false === $parsedUrl = parse_url($dsn)) {
            throw new InvalidArgumentException(sprintf('The given Amazon SQS DSN "%s" is invalid.', $dsn));
        }

        $query = [];
        if (isset($parsedUrl['query'])) {
            parse_str($parsedUrl['query'], $query);
        }

        $configuration = [
            'access_token' => $options['access_token'] ?? (urldecode($parsedUrl['token'] ?? '') ?: self::DEFAULT_OPTIONS['access_token']),
            'api_key' => $options['api_key'] ?? (urldecode($parsedUrl['key'] ?? '') ?: self::DEFAULT_OPTIONS['api_key']),
            'topic' => $options['topic'] ?? (urldecode($parsedUrl['topic'] ?? '') ?: self::DEFAULT_OPTIONS['topic']),
        ];

        return new self($configuration, $client);
    }

    public function createTopic(string $topic = self::DEFAULT_OPTIONS['topic'], array $body = []): void
    {
        $response = $this->client->request('PUT', sprintf('%s/v1/%s', self::DEFAULT_OPTIONS['endpoint'], $topic), [
            'body' => $body,
        ]);
    }

    public function create(string $topic, array $headers, int $delay = 0): void
    {

    }
}
