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

use Symfony\Component\Scheduler\Bridge\Doctrine\Transport\DoctrineTransportFactory;
use Symfony\Component\Scheduler\Bridge\Redis\Transport\RedisTransportFactory;

return static function (ContainerConfigurator $container): void {
    $container->services()
        // Doctrine
        ->set('scheduler.transport_factory.doctrine', DoctrineTransportFactory::class)
            ->args([
                service('doctrine'),
            ])
            ->tag('scheduler.extra', [
                'require' => 'doctrine',
                'tag' => 'scheduler.transport_factory'
            ])

        // Redis
        ->set('scheduler.transport_factory.redis', RedisTransportFactory::class)
    ;
};
