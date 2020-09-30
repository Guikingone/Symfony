<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Test\Constraint;

use PHPUnit\Framework\Constraint\Constraint;
use Symfony\Component\Scheduler\Event\TaskEventList;
use Symfony\Component\Scheduler\Event\TaskScheduledEvent;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.3
 */
final class TaskQueued extends Constraint
{
    private $expectedCount;

    public function __construct(int $expectedCount)
    {
        $this->expectedCount = $expectedCount;
    }

    /**
     * {@inheritdoc}
     */
    public function toString(): string
    {
        return sprintf('%s %s been queued', $this->expectedCount, $this->expectedCount > 1 ? 'have' : 'has');
    }

    /**
     * @param TaskEventList $eventsList
     *
     * {@inheritdoc}
     */
    protected function matches($eventsList): bool
    {
        return $this->expectedCount === $this->countQueuedTasks($eventsList);
    }

    private function countQueuedTasks(TaskEventList $eventsList): int
    {
        $count = 0;
        foreach ($eventsList->getEvents() as $event) {
            if (!$event instanceof TaskScheduledEvent || !$event->getTask()->isQueued()) {
                continue;
            }

            ++$count;
        }

        return $count;
    }
}
