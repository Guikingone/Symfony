<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Transport;

use Symfony\Component\Scheduler\Exception\LogicException;
use Symfony\Component\Scheduler\SchedulePolicy\SchedulePolicyOrchestratorInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.3
 */
final class RoundRobinTransportFactory implements TransportFactoryInterface
{
    private $transportFactories;

    /**
     * @param iterable|TransportFactoryInterface[] $transportFactories
     */
    public function __construct(iterable $transportFactories)
    {
        $this->transportFactories = $transportFactories;
    }

    /**
     * {@inheritdoc}
     */
    public function createTransport(Dsn $dsn, array $options, SerializerInterface $serializer, SchedulePolicyOrchestratorInterface $schedulePolicyOrchestrator): TransportInterface
    {
        $dsnList = $dsn->getOptions()[0] ?? [];
        if (empty($dsnList)) {
            throw new LogicException('The round robin transport cannot be created');
        }

        $transportsDsn = explode(' && ', $dsnList);

        $transports = [];
        array_walk($transportsDsn, function (string $dsn) use ($transports, $options, $serializer, $schedulePolicyOrchestrator): void {
            foreach ($this->transportFactories as $transportFactory) {
                if (!$transportFactory->support($dsn)) {
                    continue;
                }

                $transports[] = $transportFactory->createTransport(Dsn::fromString($dsn), $options, $serializer, $schedulePolicyOrchestrator);
            }
        });

        return new RoundRobinTransport($transports);
    }

    /**
     * {@inheritdoc}
     */
    public function support(string $dsn, array $options = []): bool
    {
        return 0 === strpos($dsn, 'roundrobin://') || 0 === strpos($dsn, 'rr://');
    }
}
