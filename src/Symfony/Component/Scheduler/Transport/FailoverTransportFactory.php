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

use Symfony\Component\Scheduler\SchedulePolicy\SchedulePolicyOrchestratorInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.3
 */
final class FailoverTransportFactory implements TransportFactoryInterface
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
        $dsnList = $dsn->getOptions()[0];
        $transportsDsn = explode(' || ', $dsnList);

        $transports = [];
        array_walk($transportsDsn, function (string $dsn) use ($transports, $options, $serializer, $schedulePolicyOrchestrator): void {
            foreach ($this->transportFactories as $transportFactory) {
                if (!$transportFactory->support($dsn)) {
                    continue;
                }

                $transports[] = $transportFactory->createTransport(Dsn::fromString($dsn), $options, $serializer, $schedulePolicyOrchestrator);
            }
        });

        return new FailoverTransport($transports);
    }

    /**
     * {@inheritdoc}
     */
    public function support(string $dsn, array $options = []): bool
    {
        return 0 === strpos($dsn, 'failover://') || 0 === strpos($dsn, 'fo://');
    }
}
