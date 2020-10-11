<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.3
 */
final class SchedulerPass implements CompilerPassInterface
{
    private $schedulerTaskTag;

    public function __construct(string $schedulerTaskTag = 'scheduler.task')
    {
        $this->schedulerTaskTag = $schedulerTaskTag;
    }

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        $this->removeTasks($container);
    }

    private function removeTasks(ContainerBuilder $container): void
    {
        foreach ($container->findTaggedServiceIds($this->schedulerTaskTag) as $task) {
            $container->removeDefinition($task);
        }
    }
}
