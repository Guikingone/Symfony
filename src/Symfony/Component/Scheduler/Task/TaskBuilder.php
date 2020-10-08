<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Task;

use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Scheduler\Exception\InvalidArgumentException;
use Symfony\Component\Scheduler\Task\Builder\BuilderInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.3
 */
final class TaskBuilder implements TaskBuilderInterface
{
    /**
     * @var BuilderInterface[]
     */
    private $builders;
    private $propertyAccessor;

    public function __construct(iterable $builders, PropertyAccessorInterface $propertyAccessor)
    {
        $this->builders = $builders;
        $this->propertyAccessor = $propertyAccessor;
    }

    /**
     * {@inheritdoc}
     */
    public function create(array $options = []): TaskInterface
    {
        foreach ($this->builders as $builder) {
            if (!$builder->support($options['type'])) {
                continue;
            }

            return $builder->build($this->propertyAccessor, $options);
        }

        throw new InvalidArgumentException(sprintf('The task cannot be created as no builder has been defined for "%s"', $options['type']));
    }
}
