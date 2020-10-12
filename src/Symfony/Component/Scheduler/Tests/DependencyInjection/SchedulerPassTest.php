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
use Symfony\Component\Scheduler\DependencyInjection\SchedulerPass;
use Symfony\Component\Scheduler\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SchedulerPassTest extends TestCase
{
    public function testSchedulerExtraCannotBeRegisteredWithoutDependency(): void
    {
        $container = new ContainerBuilder();
        $container->register('scheduler.foo_task', TaskInterface::class)->addTag('scheduler.extra', [
            'require' => 'scheduler.task_builder',
            'tag' => 'scheduler.tag',
        ]);

        (new SchedulerPass())->process($container);

        static::assertFalse($container->hasDefinition('scheduler.foo_task'));
    }

    public function testSchedulerExtraCanBeRegisteredWithDependency(): void
    {
        $container = new ContainerBuilder();
        $container->register('scheduler.task_builder', \stdClass::class);
        $container->register('scheduler.foo_task', TaskInterface::class)->addTag('scheduler.extra', [
            'require' => 'scheduler.task_builder',
            'tag' => 'scheduler.tag',
        ]);

        (new SchedulerPass())->process($container);

        static::assertTrue($container->hasDefinition('scheduler.foo_task'));
        static::assertTrue($container->getDefinition('scheduler.foo_task')->hasTag('scheduler.tag'));
    }
}
