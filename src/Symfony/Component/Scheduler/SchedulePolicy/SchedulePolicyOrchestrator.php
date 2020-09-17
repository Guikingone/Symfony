<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\SchedulePolicy;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.3
 */
final class SchedulePolicyOrchestrator implements SchedulePolicyOrchestratorInterface
{
    /**
     * @var PolicyInterface[]
     */
    private $policies;

    public function __construct(iterable $policies)
    {
        $this->policies = $policies;
    }

    /**
     * {@inheritdoc}
     */
    public function sort(string $policy, array $tasks): array
    {
        if (empty($this->policies)) {
            throw new \RuntimeException('The tasks cannot be sorted as no policies have been defined');
        }

        if (empty($tasks)) {
            return [];
        }

        foreach ($this->policies as $schedulePolicy) {
            if (!$schedulePolicy->support($policy)) {
                continue;
            }

            return $schedulePolicy->sort($tasks);
        }

        throw new \InvalidArgumentException(sprintf('The policy "%s" cannot be used', $policy));
    }
}
