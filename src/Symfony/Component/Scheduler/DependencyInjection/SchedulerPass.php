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
use Symfony\Component\DependencyInjection\Reference;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.2
 */
final class SchedulerPass implements CompilerPassInterface
{
    private $schedulerEntryPointTag;

    public function __construct(string $schedulerEntryPointTag = 'scheduler.entry_point')
    {
        $this->schedulerEntryPointTag = $schedulerEntryPointTag;
    }

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        $this->registerSchedulerEntrypoint($container);
    }

    private function registerSchedulerEntryPoint(ContainerBuilder $container): void
    {
        foreach ($container->findTaggedServiceIds($this->schedulerEntryPointTag) as $entryPointsId => $tags) {
            $container->getDefinition($entryPointsId)->addMethodCall('schedule', [new Reference('scheduler.scheduler')]);
        }
    }
}
