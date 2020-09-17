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

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.3
 */
final class ShellTask extends AbstractTask
{
    public function __construct(string $name, array $command, string $cwd = null, array $environmentVariables = [], float $timeout = 60)
    {
        $this->defineOptions([
            'command' => $command,
            'cwd' => $cwd,
            'environment_variables' => $environmentVariables,
            'timeout' => $timeout,
        ], [
            'command' => ['string[]', 'array'],
            'cwd' => ['string', 'null'],
            'environment_variables' => ['string[]', 'array'],
            'timeout' => ['float'],
        ]);

        parent::__construct($name);
    }

    public function getCommand(): array
    {
        return $this->options['command'];
    }

    public function setCommand(array $command): TaskInterface
    {
        $this->options['command'] = $command;

        return $this;
    }

    public function getCwd(): ?string
    {
        return $this->options['cwd'];
    }

    public function setCwd(?string $cwd): TaskInterface
    {
        $this->options['cwd'] = $cwd;

        return $this;
    }

    public function getEnvironmentVariables(): array
    {
        return $this->options['environment_variables'];
    }

    public function setEnvironmentVariables(array $environmentVariables): TaskInterface
    {
        $this->options['environment_variables'] = $environmentVariables;

        return $this;
    }

    public function getTimeout(): ?float
    {
        return $this->options['timeout'];
    }

    public function setTimeout(float $timeout): TaskInterface
    {
        $this->options['timeout'] = $timeout;

        return $this;
    }
}
