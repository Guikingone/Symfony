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
interface TransportFactoryInterface
{
    /**
     * @param Dsn                                 $dsn
     * @param array<string,int|string|bool|array> $options
     * @param SerializerInterface                 $serializer
     * @param SchedulePolicyOrchestratorInterface $schedulePolicyOrchestrator
     *
     * @return TransportInterface
     */
    public function createTransport(Dsn $dsn, array $options, SerializerInterface $serializer, SchedulePolicyOrchestratorInterface $schedulePolicyOrchestrator): TransportInterface;

    /**
     * @param string                              $dsn
     * @param array<string,int|string|bool|array> $options
     *
     * @return bool
     */
    public function support(string $dsn, array $options = []): bool;
}
