<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Scheduler\DataCollector\SchedulerDataCollector;
use Symfony\Component\Scheduler\DependencyInjection\SchedulerPass;
use Symfony\Component\Scheduler\Scheduler;
use Symfony\Component\Scheduler\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SchedulerPassTest extends TestCase
{
    public function testEntryPointCanBeGeneratedWithValidEntryPoints(): void
    {
        $container = $this->getContainerBuilder();
        $container->register('scheduler.foo_task', TaskInterface::class)->addTag('scheduler.entry_point');

        (new SchedulerPass())->process($container);
        static::assertTrue($container->hasDefinition('scheduler.foo_entry_point'));
        static::assertTrue($container->getDefinition('scheduler.foo_entry_point')->hasMethodCall('schedule'));
    }

    private function getContainerBuilder(string $schedulerId = 'scheduler'): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', true);
        $container->register($schedulerId, Scheduler::class)->addTag('scheduler.hub');
        if ('scheduler' !== $schedulerId) {
            $container->setAlias('scheduler', $schedulerId);
        }
        $container->register('scheduler.data_collector', SchedulerDataCollector::class);

        return $container;
    }
}
