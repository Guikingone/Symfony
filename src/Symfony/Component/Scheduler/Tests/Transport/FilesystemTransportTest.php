<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Scheduler\Exception\InvalidArgumentException;
use Symfony\Component\Scheduler\Exception\LogicException;
use Symfony\Component\Scheduler\Serializer\TaskNormalizer;
use Symfony\Component\Scheduler\Task\NullTask;
use Symfony\Component\Scheduler\Task\ShellTask;
use Symfony\Component\Scheduler\Task\TaskInterface;
use Symfony\Component\Scheduler\Transport\FilesystemTransport;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\DateIntervalNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeZoneNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class FilesystemTransportTest extends TestCase
{
    private $filesystem;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->filesystem->remove(__DIR__.'/assets/**/*.json');
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown(): void
    {
        $this->filesystem->remove(__DIR__.'/assets/bar.json');
        $this->filesystem->remove(__DIR__.'/assets/foo.json');
    }

    public function testTaskListCanBeRetrieved(): void
    {
        $objectNormalizer = new ObjectNormalizer();

        $serializer = new Serializer([new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objectNormalizer), $objectNormalizer], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $transport = new FilesystemTransport(__DIR__.'/assets', [], $serializer);

        $transport->create(new NullTask('bar'));
        static::assertTrue($this->filesystem->exists(__DIR__.'/assets/bar.json'));

        $list = $transport->list();
        static::assertNotEmpty($list);
        static::assertInstanceOf(NullTask::class, $list->get('bar'));
    }

    public function testTaskCannotBeRetrievedWithUndefinedTask(): void
    {
        $objectNormalizer = new ObjectNormalizer();

        $serializer = new Serializer([new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objectNormalizer), $objectNormalizer], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $transport = new FilesystemTransport(__DIR__.'/assets', [], $serializer);

        static::expectException(InvalidArgumentException::class);
        static::expectExceptionMessage('The "bar" task does not exist');
        $transport->get('bar');
    }

    public function testTaskCanBeRetrieved(): void
    {
        $objectNormalizer = new ObjectNormalizer();

        $serializer = new Serializer([new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objectNormalizer), $objectNormalizer], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $transport = new FilesystemTransport(__DIR__.'/assets', [], $serializer);

        $transport->create(new NullTask('foo'));
        static::assertTrue($this->filesystem->exists(__DIR__.'/assets/foo.json'));

        $task = $transport->get('foo');
        static::assertInstanceOf(NullTask::class, $task);
        static::assertSame('foo', $task->getName());
        static::assertSame('* * * * *', $task->getExpression());
    }

    public function testTaskCanBeCreated(): void
    {
        $objectNormalizer = new ObjectNormalizer();

        $serializer = new Serializer([new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objectNormalizer), $objectNormalizer], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $transport = new FilesystemTransport(__DIR__.'/assets', [], $serializer);

        $transport->create(new NullTask('foo'));
        static::assertTrue($this->filesystem->exists(__DIR__.'/assets/foo.json'));
    }

    public function testTaskCannotBeUpdatedWithUndefinedTask(): void
    {
        $objectNormalizer = new ObjectNormalizer();

        $serializer = new Serializer([new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objectNormalizer), $objectNormalizer], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $transport = new FilesystemTransport(__DIR__.'/assets', [], $serializer);

        static::expectException(InvalidArgumentException::class);
        static::expectExceptionMessage('The "foo" task does not exist');
        $transport->update('foo', new NullTask('foo'));
    }

    public function testTaskCanBeUpdated(): void
    {
        $objectNormalizer = new ObjectNormalizer();

        $serializer = new Serializer([new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objectNormalizer), $objectNormalizer], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $transport = new FilesystemTransport(__DIR__.'/assets', [], $serializer);

        $transport->create(new ShellTask('foo', ['echo', 'Symfony']));
        static::assertTrue($this->filesystem->exists(__DIR__.'/assets/foo.json'));

        $task = $transport->get('foo');
        $task->setExpression('0 * * * *');
        static::assertSame('0 * * * *', $task->getExpression());

        $transport->update('foo', $task);
        $updatedTask = $transport->get('foo');

        static::assertSame('0 * * * *', $updatedTask->getExpression());
        static::assertTrue($this->filesystem->exists(__DIR__.'/assets/foo.json'));
    }

    public function testTaskCanBeDeleted(): void
    {
        $objectNormalizer = new ObjectNormalizer();

        $serializer = new Serializer([new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objectNormalizer), $objectNormalizer], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $transport = new FilesystemTransport(__DIR__.'/assets', [], $serializer);

        $transport->create(new NullTask('foo'));
        static::assertTrue($this->filesystem->exists(__DIR__.'/assets/foo.json'));

        $transport->delete('foo');
        static::assertFalse($this->filesystem->exists(__DIR__.'/assets/foo.json'));
    }

    public function testTaskCannotBePausedTwice(): void
    {
        $objectNormalizer = new ObjectNormalizer();

        $serializer = new Serializer([new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objectNormalizer), $objectNormalizer], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $transport = new FilesystemTransport(__DIR__.'/assets', [], $serializer);

        $transport->create(new NullTask('foo'));
        static::assertTrue($this->filesystem->exists(__DIR__.'/assets/foo.json'));

        $transport->pause('foo');

        $task = $transport->get('foo');
        static::assertSame(TaskInterface::PAUSED, $task->getState());

        static::expectException(LogicException::class);
        static::expectExceptionMessage('The task "foo" is already paused');
        $transport->pause('foo');
    }

    public function testTaskCanBePaused(): void
    {
        $objectNormalizer = new ObjectNormalizer();

        $serializer = new Serializer([new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objectNormalizer), $objectNormalizer], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $transport = new FilesystemTransport(__DIR__.'/assets', [], $serializer);

        $transport->create(new NullTask('foo'));
        static::assertTrue($this->filesystem->exists(__DIR__.'/assets/foo.json'));

        $transport->pause('foo');

        $task = $transport->get('foo');
        static::assertSame(TaskInterface::PAUSED, $task->getState());
    }

    public function testTaskCannotBeResumedTwice(): void
    {
        $objectNormalizer = new ObjectNormalizer();

        $serializer = new Serializer([new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objectNormalizer), $objectNormalizer], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $transport = new FilesystemTransport(__DIR__.'/assets', [], $serializer);

        $transport->create(new NullTask('foo'));
        static::assertTrue($this->filesystem->exists(__DIR__.'/assets/foo.json'));

        $transport->pause('foo');

        $task = $transport->get('foo');
        static::assertSame(TaskInterface::PAUSED, $task->getState());

        $transport->resume('foo');
        $task = $transport->get('foo');
        static::assertSame(TaskInterface::ENABLED, $task->getState());

        static::expectException(LogicException::class);
        static::expectExceptionMessage('The task "foo" is already enabled');
        $transport->resume('foo');
    }

    public function testTaskCanBeResumed(): void
    {
        $objectNormalizer = new ObjectNormalizer();

        $serializer = new Serializer([new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objectNormalizer), $objectNormalizer], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $transport = new FilesystemTransport(__DIR__.'/assets', [], $serializer);

        $transport->create(new NullTask('foo'));
        static::assertTrue($this->filesystem->exists(__DIR__.'/assets/foo.json'));

        $transport->pause('foo');

        $task = $transport->get('foo');
        static::assertSame(TaskInterface::PAUSED, $task->getState());

        $transport->resume('foo');

        $task = $transport->get('foo');
        static::assertSame(TaskInterface::ENABLED, $task->getState());
    }

    public function testTaskCanBeCleared(): void
    {
        $objectNormalizer = new ObjectNormalizer();

        $serializer = new Serializer([new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objectNormalizer), $objectNormalizer], [new JsonEncoder()]);
        $objectNormalizer->setSerializer($serializer);

        $transport = new FilesystemTransport(__DIR__.'/assets', [], $serializer);

        $transport->create(new NullTask('foo'));
        $transport->create(new NullTask('bar'));
        static::assertTrue($this->filesystem->exists(__DIR__.'/assets/foo.json'));
        static::assertTrue($this->filesystem->exists(__DIR__.'/assets/bar.json'));

        $transport->clear();
        static::assertFalse($this->filesystem->exists(__DIR__.'/assets/foo.json'));
        static::assertFalse($this->filesystem->exists(__DIR__.'/assets/bar.json'));
     }
}
