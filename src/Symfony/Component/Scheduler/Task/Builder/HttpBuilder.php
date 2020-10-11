<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Task\Builder;

use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Scheduler\Task\HttpTask;
use Symfony\Component\Scheduler\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.3
 */
final class HttpBuilder implements BuilderInterface
{
    /**
     * {@inheritdoc}
     */
    public function build(PropertyAccessorInterface $propertyAccessor, array $options = []): TaskInterface
    {
        $task = new HttpTask($options['name'], $options['url'], $options['method'] ?? null, $options['client_options'] ?? []);

        foreach ($options as $option => $value) {
            if (!$propertyAccessor->isWritable($task, $option)) {
                continue;
            }

            $propertyAccessor->setValue($task, $option, $value);
        }

        return $task;
    }

    /**
     * {@inheritdoc}
     */
    public function support(?string $type = null): bool
    {
        return 'http' === $type;
    }
}
