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
        'timezone' => 'UTC',
        'path' => '/_tasks',
        'transport' => [
            'dsn' => 'memory://first_in_first_out',
        ],
        'tasks' => [
            'foo' => [
                'type' => 'http',
                'expression' => '*/5 * * * *',
                'command' => 'cache:clear',
                'url' => 'https://symfony.com',
                'method' => 'GET',
            ],
        ],
    ],
]);
