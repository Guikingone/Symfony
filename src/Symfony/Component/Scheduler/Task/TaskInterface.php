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
interface TaskInterface
{
    public const ENABLED = 'enabled';
    public const PAUSED = 'paused';
    public const DISABLED = 'disabled';
    public const UNDEFINED = 'undefined';
    public const ALLOWED_STATES = [
        self::ENABLED,
        self::PAUSED,
        self::DISABLED,
        self::UNDEFINED,
    ];

    public const SUCCEED = 'succeed';
    public const RUNNING = 'running';
    public const DONE = 'done';
    public const INCOMPLETE = 'incomplete';
    public const ERRORED = 'errored';
    public const TO_RETRY = 'to_retry';
    public const EXECUTION_STATES = [
        self::SUCCEED,
        self::RUNNING,
        self::DONE,
        self::INCOMPLETE,
        self::ERRORED,
        self::TO_RETRY,
    ];

    public function getName(): string;

    public function setName(string $name): TaskInterface;

    public function setArrivalTime(\DateTimeImmutable $arrivalTime = null): TaskInterface;

    public function getArrivalTime(): ?\DateTimeImmutable;

    public function setBackground(bool $background): TaskInterface;

    public function mustRunInBackground(): bool;

    public function setDescription(string $description = null): TaskInterface;

    public function getDescription(): ?string;

    public function setExpression(string $expression): TaskInterface;

    public function getExpression(): string;

    public function setExecutionAbsoluteDeadline(\DateInterval $executionAbsoluteDeadline = null): TaskInterface;

    public function getExecutionAbsoluteDeadline(): ?\DateInterval;

    public function getExecutionComputationTime(): ?float;

    public function setExecutionComputationTime(float $executionComputationTime = null): TaskInterface;

    public function getExecutionMemoryUsage(): ?int;

    public function setExecutionMemoryUsage(int $executionMemoryUsage = null): TaskInterface;

    public function getExecutionPeriod(): ?float;

    public function setExecutionPeriod(float $executionPeriod = null): TaskInterface;

    public function getExecutionRelativeDeadline(): ?\DateInterval;

    public function setExecutionRelativeDeadline(\DateInterval $executionRelativeDeadline = null): TaskInterface;

    public function setExecutionStartTime(\DateTimeImmutable $executionStartTime = null): TaskInterface;

    public function getExecutionStartTime(): ?\DateTimeImmutable;

    public function setExecutionEndTime(\DateTimeImmutable $executionStartTime = null): TaskInterface;

    public function getExecutionEndTime(): ?\DateTimeImmutable;

    public function setLastExecution(\DateTimeImmutable $lastExecution = null): TaskInterface;

    public function getLastExecution(): ?\DateTimeImmutable;

    public function setMaxDuration(float $maxDuration = null): TaskInterface;

    public function getMaxDuration(): ?float;

    public function getNice(): ?int;

    public function setNice(int $nice = null): TaskInterface;

    public function getState(): string;

    public function setState(string $state): TaskInterface;

    public function getExecutionState(): ?string;

    public function setExecutionState(string $executionState = null): TaskInterface;

    public function isOutput(): bool;

    public function setOutput(bool $output): TaskInterface;

    public function getPriority(): int;

    public function setPriority(int $priority): TaskInterface;

    public function isQueued(): bool;

    public function setQueued(bool $queued): TaskInterface;

    public function setScheduledAt(\DateTimeImmutable $scheduledAt): TaskInterface;

    public function getScheduledAt(): ?\DateTimeImmutable;

    public function isSingleRun(): bool;

    public function setSingleRun(bool $singleRun): TaskInterface;

    public function getTags(): array;

    public function setTags(array $tags): TaskInterface;

    public function addTag(string $tag): TaskInterface;

    public function getTimezone(): ?\DateTimeZone;

    public function setTimezone(string $timezone = null): TaskInterface;

    public function isTracked(): bool;

    public function setTracked(bool $tracked): TaskInterface;
}
