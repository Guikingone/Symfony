<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\OAuth\Exception;

/**
 * Thrown if the provider does not receive all the required options.
 *
 * {@see GenericProvider::defineOptions}
 *
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class MissingOptionsException extends \RuntimeException
{
}
