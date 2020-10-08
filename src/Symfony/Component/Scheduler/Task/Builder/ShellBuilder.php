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

use Symfony\Component\Scheduler\Task\ShellTask;
use Symfony\Component\Scheduler\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.3
 */
final class ShellBuilder implements BuilderInterface
{
    /**
     * {@inheritdoc}
     */
    public function build(PropertyAccessorInterface $propertyAccessor, array $options = []): TaskInterface
    {
        $task = new ShellTask($options['name'], $options['command'], $options['cwd'] ?? null, $options['environment_variables'] ?? [], $options['timeout'] ?? 60);

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
    public function support(string $type): bool
    {
        return 'shell' === $type;
    }
}
