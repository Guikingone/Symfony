<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Tests\Task;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\Exception\InvalidArgumentException;
use Symfony\Component\Scheduler\Task\NullTask;
use Symfony\Component\Scheduler\Task\ShellTask;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class NullTaskTest extends TestCase
{
    public function testTaskCanBeCreatedWithValidInformations(): void
    {
        static::assertSame('foo', (new NullTask('foo'))->getName());
    }

    public function testTaskCanBeCreatedWithBackgroundOption(): void
    {
        $task = new NullTask('foo');

        static::expectException(InvalidArgumentException::class);
        static::expectExceptionMessage(sprintf('The background option is available only for task of type %s', ShellTask::class));
        $task->setBackground(true);
    }

    /**
     * @dataProvider provideNice
     */
    public function testTaskCannotBeCreatedWithInvalidNice(int $nice): void
    {
        $task = new NullTask('foo');

        static::expectException(InvalidArgumentException::class);
        static::expectExceptionMessage('The nice value is not valid');
        $task->setNice($nice);
    }

    public function provideNice(): \Generator
    {
        yield [20];
        yield [-25];
        yield [200];
    }
}
