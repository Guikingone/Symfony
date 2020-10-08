<?php

require __DIR__.'/vendor/autoload.php';

use Psr\Log\LogLevel;
use Symfony\Component\Console\Application;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\Log\Logger;
use Symfony\Component\Scheduler\Command\ConsumeTasksCommand;
use Symfony\Component\Scheduler\Command\ListTasksCommand;
use Symfony\Component\Scheduler\EventListener\TaskExecutionSubscriber;
use Symfony\Component\Scheduler\Runner\NullTaskRunner;
use Symfony\Component\Scheduler\Runner\ShellTaskRunner;
use Symfony\Component\Scheduler\Scheduler;
use Symfony\Component\Scheduler\Task\NullTask;
use Symfony\Component\Scheduler\Task\ShellTask;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Scheduler\Messenger\TaskMessage;
use Symfony\Component\Scheduler\Messenger\TaskMessageHandler;
use Symfony\Component\Scheduler\Serializer\TaskNormalizer;
use Symfony\Component\Scheduler\Task\TaskExecutionTracker;
use Symfony\Component\Scheduler\Transport\FilesystemTransport;
use Symfony\Component\Scheduler\Worker\Worker;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\DateIntervalNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeZoneNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

$objNorm = new ObjectNormalizer();
$norm = new TaskNormalizer(new DateTimeNormalizer(), new DateTimeZoneNormalizer(), new DateIntervalNormalizer(), $objNorm);
$transport = new FilesystemTransport(__DIR__.'/tmp/_sf_scheduler', [], new Serializer([$norm, $objNorm], [new JsonEncoder()]));
$scheduler = new Scheduler('Europe/Paris', $transport, null, new MessageBus());

$logger = new Logger(LogLevel::DEBUG, 'php://stderr');

$dispatcher = new EventDispatcher();
$dispatcher->addSubscriber(new TaskExecutionSubscriber($scheduler));

$worker = new Worker(
    $scheduler,
    [
        new ShellTaskRunner(),
        new NullTaskRunner(),
    ],
    new TaskExecutionTracker(new Stopwatch()),
    $dispatcher,
    $logger
);

$bus = new MessageBus([
    new HandleMessageMiddleware(new HandlersLocator([
        TaskMessage::class => [new TaskMessageHandler($worker)],
    ])),
]);

$task = new ShellTask('app.test', ['echo', 'Symfony']);
$task->setExpression('*/5 * * * *');
$task->setOutput(true);
//$scheduler->schedule($task);

$task = new ShellTask('me.test', ['echo', 'Me']);
$task->setOutput(true);
//$scheduler->schedule($task);

//$scheduler->schedule(new NullTask('foo'));

$app = new Application();
$app->add(new ConsumeTasksCommand($scheduler, $worker, $dispatcher));
$app->add(new ListTasksCommand($scheduler));
$app->run();
