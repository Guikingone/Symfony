<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

$container->loadFromExtension('framework', [
    'scheduler' => [
        'path' => '/_tasks',
        'transport' => [
            'dsn' => 'failover://(memory://first_in_first_out || memory://last_in_first_out)',
        ],
    ],
]);
