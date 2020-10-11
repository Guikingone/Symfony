<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Scheduler\Command\ConsumeTasksCommand;
use Symfony\Component\Scheduler\Command\ListFailedTasksCommand;
use Symfony\Component\Scheduler\Command\ListTasksCommand;
use Symfony\Component\Scheduler\Command\RebootSchedulerCommand;
use Symfony\Component\Scheduler\Command\RemoveFailedTaskCommand;
use Symfony\Component\Scheduler\Command\RetryFailedTaskCommand;
use Symfony\Component\Scheduler\EventListener\StopWorkerOnSignalSubscriber;
use Symfony\Component\Scheduler\EventListener\TaskExecutionSubscriber;
use Symfony\Component\Scheduler\EventListener\TaskLoggerSubscriber;
use Symfony\Component\Scheduler\SchedulePolicy\BatchPolicy;
use Symfony\Component\Scheduler\SchedulePolicy\DeadlinePolicy;
use Symfony\Component\Scheduler\SchedulePolicy\ExecutionDurationPolicy;
use Symfony\Component\Scheduler\SchedulePolicy\FirstInFirstOutPolicy;
use Symfony\Component\Scheduler\SchedulePolicy\FirstInLastOutPolicy;
use Symfony\Component\Scheduler\SchedulePolicy\IdlePolicy;
use Symfony\Component\Scheduler\SchedulePolicy\MemoryUsagePolicy;
use Symfony\Component\Scheduler\SchedulePolicy\NicePolicy;
use Symfony\Component\Scheduler\SchedulePolicy\RoundRobinPolicy;
use Symfony\Component\Scheduler\SchedulePolicy\SchedulePolicyOrchestrator;
use Symfony\Component\Scheduler\SchedulePolicy\SchedulePolicyOrchestratorInterface;
use Symfony\Component\Scheduler\Task\Builder\CommandBuilder;
use Symfony\Component\Scheduler\Task\Builder\HttpBuilder;
use Symfony\Component\Scheduler\Task\Builder\NullBuilder;
use Symfony\Component\Scheduler\Task\Builder\ShellBuilder;
use Symfony\Component\Scheduler\Task\TaskBuilder;
use Symfony\Component\Scheduler\Task\TaskBuilderInterface;
use Symfony\Component\Scheduler\Transport\FilesystemTransportFactory;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Scheduler\EventListener\TaskSubscriber;
use Symfony\Component\Scheduler\Expression\ExpressionFactory;
use Symfony\Component\Scheduler\Messenger\TaskMessageHandler;
use Symfony\Component\Scheduler\Runner\CallbackTaskRunner;
use Symfony\Component\Scheduler\Runner\CommandTaskRunner;
use Symfony\Component\Scheduler\Runner\HttpTaskRunner;
use Symfony\Component\Scheduler\Runner\MessengerTaskRunner;
use Symfony\Component\Scheduler\Runner\NotificationTaskRunner;
use Symfony\Component\Scheduler\Runner\NullTaskRunner;
use Symfony\Component\Scheduler\Runner\ShellTaskRunner;
use Symfony\Component\Scheduler\Serializer\TaskNormalizer;
use Symfony\Component\Scheduler\Task\TaskExecutionTracker;
use Symfony\Component\Scheduler\Task\TaskExecutionTrackerInterface;
use Symfony\Component\Scheduler\Transport\InMemoryTransportFactory;
use Symfony\Component\Scheduler\Transport\TransportFactory;
use Symfony\Component\Scheduler\Worker\Worker;
use Symfony\Component\Scheduler\Worker\WorkerInterface;
use Symfony\Component\Stopwatch\Stopwatch;

return static function (ContainerConfigurator $container): void {
    $container->services()
        ->set('scheduler.command.consume', ConsumeTasksCommand::class)
            ->args([
                service('scheduler.scheduler'),
                service('scheduler.worker'),
                service('event_dispatcher'),
                service('logger')->nullOnInvalid(),
            ])
            ->tag('console.command')
            ->tag('monolog.logger', [
                'channel' => 'scheduler',
            ])

        ->set('scheduler.command.list_failed', ListFailedTasksCommand::class)
            ->args([
                service('scheduler.worker'),
            ])
            ->tag('console.command')

        ->set('scheduler.command.list', ListTasksCommand::class)
            ->args([
                service('scheduler.scheduler'),
            ])
            ->tag('console.command')

        ->set('scheduler.command.reboot', RebootSchedulerCommand::class)
            ->args([
                service('scheduler.scheduler'),
                service('scheduler.worker'),
                service('event_dispatcher'),
                service('logger')->nullOnInvalid(),
            ])
            ->tag('console.command')
            ->tag('monolog.logger', [
                'channel' => 'scheduler',
            ])

        ->set('scheduler.command.remove_failed', RemoveFailedTaskCommand::class)
            ->args([
                service('scheduler.scheduler'),
                service('scheduler.worker'),
            ])
            ->tag('console.command')

        ->set('scheduler.command.retry_failed', RetryFailedTaskCommand::class)
            ->args([
                service('scheduler.scheduler'),
                service('scheduler.worker'),
                service('event_dispatcher'),
                service('logger')->nullOnInvalid(),
            ])
            ->tag('console.command')
            ->tag('monolog.logger', [
                'channel' => 'scheduler',
            ])

        ->set('scheduler.application', Application::class)
            ->args([
                service('kernel'),
            ])

        // Transports factories
        ->set('scheduler.transport_factory', TransportFactory::class)
            ->args([
                tagged_iterator('scheduler.transport_factory'),
            ])

        ->set('scheduler.transport_factory.memory', InMemoryTransportFactory::class)
            ->tag('scheduler.transport_factory')

        ->set('scheduler.transport_factory.filesystem', FilesystemTransportFactory::class)
            ->tag('scheduler.transport_factory')

        // ExpressionFactory & SchedulerPolicyOrchestrator + Policies
        ->set('scheduler.expression_factory', ExpressionFactory::class)

        ->set('scheduler.schedule_policy_orchestrator', SchedulePolicyOrchestrator::class)
            ->args([
                tagged_iterator('scheduler.schedule_policy')
            ])
        ->alias(SchedulePolicyOrchestratorInterface::class, 'scheduler.schedule_policy_orchestrator')

        ->set('scheduler.batch_policy', BatchPolicy::class)
            ->tag('scheduler.schedule_policy')

        ->set('scheduler.deadline_policy', DeadlinePolicy::class)
            ->tag('scheduler.schedule_policy')

        ->set('scheduler.execution_duration_policy', ExecutionDurationPolicy::class)
            ->tag('scheduler.schedule_policy')

        ->set('scheduler.first_in_first_out_policy', FirstInFirstOutPolicy::class)
            ->tag('scheduler.schedule_policy')

        ->set('scheduler.first_in_last_out_policy', FirstInLastOutPolicy::class)
            ->tag('scheduler.schedule_policy')

        ->set('scheduler.idle_policy', IdlePolicy::class)
            ->tag('scheduler.schedule_policy')

        ->set('scheduler.memory_policy', MemoryUsagePolicy::class)
            ->tag('scheduler.schedule_policy')

        ->set('scheduler.nice_policy', NicePolicy::class)
            ->tag('scheduler.schedule_policy')

        ->set('scheduler.round_robin_policy', RoundRobinPolicy::class)
            ->tag('scheduler.schedule_policy')

        // Builders
        ->set('scheduler.task_builder', TaskBuilder::class)
            ->args([
                tagged_iterator('scheduler.task_builder'),
                service('property_accessor'),
            ])
        ->alias(TaskBuilderInterface::class, 'scheduler.task_builder')

        ->set('scheduler.command_task_builder', CommandBuilder::class)
            ->tag('scheduler.task_builder')

        ->set('scheduler.http_task_builder', HttpBuilder::class)
            ->tag('scheduler.task_builder')

        ->set('scheduler.null_task_builder', NullBuilder::class)
            ->tag('scheduler.task_builder')

        ->set('scheduler.shell_task_builder', ShellBuilder::class)
            ->tag('scheduler.task_builder')

        // Runners
        ->set('scheduler.shell_runner', ShellTaskRunner::class)
            ->tag('scheduler.runner')

        ->set('scheduler.command_runner', CommandTaskRunner::class)
            ->args([
                service('scheduler.application'),
            ])
            ->tag('scheduler.runner')

        ->set('scheduler.callback_runner', CallbackTaskRunner::class)
            ->tag('scheduler.runner')

        ->set('scheduler.http_runner', HttpTaskRunner::class)
            ->args([
                service('http_client')->nullOnInvalid(),
            ])
            ->tag('scheduler.runner')

        ->set('scheduler.messenger_runner', MessengerTaskRunner::class)
            ->args([
                service(MessageBusInterface::class)->nullOnInvalid(),
            ])
            ->tag('scheduler.runner')

        ->set('scheduler.notifier_runner', NotificationTaskRunner::class)
            ->args([
                service('notifier')->nullOnInvalid(),
            ])
            ->tag('scheduler.runner')

        ->set('scheduler.null_runner', NullTaskRunner::class)
            ->tag('scheduler.runner')

        // Task normalizer
        ->set('scheduler.normalizer', TaskNormalizer::class)
            ->args([
                service('serializer.normalizer.datetime'),
                service('serializer.normalizer.dateinterval'),
                service('serializer.normalizer.object'),
            ])
            ->tag('serializer.normalizer')

        // Messenger
        ->set('scheduler.task_message.handler', TaskMessageHandler::class)
            ->args([
                service('scheduler.worker'),
            ])
            ->tag('messenger.message_handler')

        // Subscribers
        ->set('scheduler.task_subscriber', TaskSubscriber::class)
            ->args([
                service('scheduler.scheduler'),
                service('scheduler.worker'),
                service('event_dispatcher'),
                service('serializer'),
                service('logger')->nullOnInvalid(),
            ])
            ->tag('kernel.event_subscriber')
            ->tag('monolog.logger', [
                'channel' => 'scheduler',
            ])

        ->set('scheduler.task_execution.subscriber', TaskExecutionSubscriber::class)
            ->args([
                service('scheduler.scheduler'),
            ])
            ->tag('kernel.event_subscriber')

        ->set('scheduler.task_logger.subscriber', TaskLoggerSubscriber::class)
            ->tag('kernel.event_subscriber')

        ->set('scheduler.stop_worker_signal.subscriber', StopWorkerOnSignalSubscriber::class)
            ->tag('kernel.event_subscriber')

        // Tracker
        ->set('scheduler.stop_watch', Stopwatch::class)
        ->set('scheduler.task_execution.tracker', TaskExecutionTracker::class)
            ->args([
                service('scheduler.stop_watch'),
            ])
        ->alias(TaskExecutionTrackerInterface::class, 'scheduler.task_execution.tracker')

        // Worker
        ->set('scheduler.worker', Worker::class)
            ->args([
                service('scheduler.scheduler'),
                tagged_iterator('scheduler.runner'),
                service('scheduler.task_execution.tracker'),
                service('event_dispatcher')->nullOnInvalid(),
                service('logger')->nullOnInvalid(),
            ])
            ->tag('monolog.logger', [
                'channel' => 'scheduler',
            ])
        ->alias(WorkerInterface::class, 'scheduler.worker')
    ;
};
