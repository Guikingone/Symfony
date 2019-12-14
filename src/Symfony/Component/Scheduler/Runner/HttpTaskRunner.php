<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Runner;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Scheduler\Task\HttpTask;
use Symfony\Component\Scheduler\Task\Output;
use Symfony\Component\Scheduler\Task\TaskInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.2
 */
final class HttpTaskRunner implements RunnerInterface
{
    private $httpClient;

    public function __construct(HttpClientInterface $httpClient = null)
    {
        $this->httpClient = $httpClient ?? HttpClient::create();
    }

    /**
     * {@inheritdoc}
     */
    public function run(TaskInterface $task): Output
    {
        try {
            $response = $this->httpClient->request($task->getMethod(), $task->getUrl(), $task->getClientOptions());

            return new Output($task, $response->getContent());
        } catch (\Throwable $exception) {
            return new Output($task, $exception->getMessage(), Output::ERROR);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function support(TaskInterface $task): bool
    {
        return $task instanceof HttpTask;
    }
}
