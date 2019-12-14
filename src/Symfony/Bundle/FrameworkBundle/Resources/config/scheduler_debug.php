<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Component\Scheduler\DataCollector\SchedulerDataCollector;

return static function (ContainerConfigurator $container): void {
    $container->services()
        ->set('scheduler.data_collector', SchedulerDataCollector::class)
        ->args([
            service('scheduler.task_logger.subscriber'),
        ])
        ->tag('data_collector', [
            'id' => SchedulerDataCollector::NAME,
            'template' => '@WebProfiler/Collector/scheduler.html.twig',
            'priority' => 255,
        ])
    ;
};
