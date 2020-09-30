<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Serializer;

use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Recipient\Recipient;
use Symfony\Component\Scheduler\Exception\InvalidArgumentException;
use Symfony\Component\Scheduler\Task\CallbackTask;
use Symfony\Component\Scheduler\Task\CommandTask;
use Symfony\Component\Scheduler\Task\HttpTask;
use Symfony\Component\Scheduler\Task\MessengerTask;
use Symfony\Component\Scheduler\Task\NotificationTask;
use Symfony\Component\Scheduler\Task\NullTask;
use Symfony\Component\Scheduler\Task\ShellTask;
use Symfony\Component\Scheduler\Task\TaskInterface;
use Symfony\Component\Scheduler\Worker\Worker;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\DateIntervalNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeZoneNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @experimental in 5.3
 */
final class TaskNormalizer implements DenormalizerInterface, NormalizerInterface
{
    private const NORMALIZATION_DISCRIMINATOR = 'taskInternalType';
    private const DATETIME_ATTRIBUTES = [
        'arrivalTime',
        'executionStartTime',
        'executionEndTime',
        'lastExecution',
        'scheduledAt',
    ];
    private const DATEINTERVAL_ATTRIBUTES = [
        'executionAbsoluteDeadline',
        'executionRelativeDeadline',
    ];

    private $dateTimeNormalizer;
    private $dateTimeZoneNormalizer;
    private $dateIntervalNormalizer;
    private $objectNormalizer;

    public function __construct(DateTimeNormalizer $dateTimeNormalizer, DateTimeZoneNormalizer $dateTimeZoneNormalizer, DateIntervalNormalizer $dateIntervalNormalizer, ObjectNormalizer $objectNormalizer)
    {
        $this->dateTimeNormalizer = $dateTimeNormalizer;
        $this->dateTimeZoneNormalizer = $dateTimeZoneNormalizer;
        $this->dateIntervalNormalizer = $dateIntervalNormalizer;
        $this->objectNormalizer = $objectNormalizer;
    }

    /**
     * {@inheritdoc}
     */
    public function normalize($object, string $format = null, array $context = []): array
    {
        if ($object instanceof CallbackTask && $object->getCallback() instanceof \Closure) {
            throw new InvalidArgumentException(sprintf('CallbackTask with closure cannot be sent to external transport, consider executing it thanks to "%s::execute()"', Worker::class));
        }

        $dateAttributesContext = $this->handleDateAttributes();

        if ($object instanceof MessengerTask) {
            $data = $this->objectNormalizer->normalize($object, $format, array_merge($context, $dateAttributesContext, [
                AbstractNormalizer::CALLBACKS => [
                    'message' => function ($innerObject, $outerObject, string $attributeName, string $format = null, array $context = []): array {
                        return [
                            'class' => \get_class($innerObject),
                            'payload' => $this->objectNormalizer->normalize($innerObject, $format, $context),
                        ];
                    }
                ],
            ]));

            return ['body' => $data, self::NORMALIZATION_DISCRIMINATOR => \get_class($object)];
        }

        if ($object instanceof CallbackTask) {
            $data = $this->objectNormalizer->normalize($object, $format, array_merge($context, [
                AbstractNormalizer::CALLBACKS => [
                    'callback' => function ($innerObject, $outerObject, string $attributeName, string $format = null, array $context = []): array {
                        return [
                            'class' => \is_object($innerObject[0]) ? $this->objectNormalizer->normalize($innerObject[0], $format, $context) : null,
                            'method' => $innerObject[1],
                            'type' => \get_class($innerObject[0]),
                        ];
                    }
                ],
            ]));

            return ['body' => $data, self::NORMALIZATION_DISCRIMINATOR => \get_class($object),];
        }

        $data = $this->objectNormalizer->normalize($object, $format, array_merge($context, $dateAttributesContext));

        return ['body' => $data, self::NORMALIZATION_DISCRIMINATOR => \get_class($object)];
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, string $format = null): bool
    {
        return $data instanceof TaskInterface;
    }

    /**
     * {@inheritdoc}
     */
    public function denormalize($data, string $type, string $format = null, array $context = [])
    {
        $objectType = $data[self::NORMALIZATION_DISCRIMINATOR];
        $body = $data['body'];
        $body = $this->handleDatetimeAttributes($body);

        if (CallbackTask::class === $objectType) {
            $callback = [
                $this->objectNormalizer->denormalize($body['callback']['class'], $body['callback']['type']),
                $body['callback']['method'],
            ];

            unset($body['callback']);

            return $this->objectNormalizer->denormalize($body, $objectType, $format, [
                AbstractNormalizer::DEFAULT_CONSTRUCTOR_ARGUMENTS => [
                    CallbackTask::class => [
                        'name' => $body['name'],
                        'callback' => $callback,
                        'arguments' => $body['arguments'],
                    ],
                ],
            ]);
        }

        if (CommandTask::class === $objectType) {
            return $this->objectNormalizer->denormalize($body, $objectType, $format, [
                AbstractNormalizer::DEFAULT_CONSTRUCTOR_ARGUMENTS => [
                    CommandTask::class => [
                        'name' => $body['name'],
                        'command' => $body['command'],
                        'arguments' => $body['arguments'],
                        'options' => $body['options'],
                    ],
                ],
            ]);
        }

        if (NullTask::class === $objectType) {
            return $this->objectNormalizer->denormalize($body, $objectType, $format, [
                AbstractNormalizer::DEFAULT_CONSTRUCTOR_ARGUMENTS => [
                    NullTask::class => ['name' => $body['name']],
                ],
            ]);
        }

        if (ShellTask::class === $objectType) {
            return $this->objectNormalizer->denormalize($body, $objectType, $format, [
                AbstractNormalizer::DEFAULT_CONSTRUCTOR_ARGUMENTS => [
                    ShellTask::class => [
                        'name' => $body['name'],
                        'command' => $body['command'],
                        'cwd' => $body['cwd'],
                        'environmentVariables' => $body['environmentVariables'],
                        'timeout' => $body['timeout'],
                    ],
                ],
            ]);
        }

        if (MessengerTask::class === $objectType) {
            $message = $this->objectNormalizer->denormalize($body['message']['payload'], $body['message']['class'], $format, $context);

            unset($body['message']);

            return $this->objectNormalizer->denormalize($body, $objectType, $format, [
                AbstractNormalizer::DEFAULT_CONSTRUCTOR_ARGUMENTS => [
                    MessengerTask::class => [
                        'name' => $body['name'],
                        'message' => $message,
                    ],
                ],
            ]);
        }

        if (NotificationTask::class === $objectType) {
            $notification = $this->objectNormalizer->denormalize($body['notification'], Notification::class, $format, $context);
            $recipients = $this->objectNormalizer->denormalize($body['recipients'], Recipient::class, $format, array_merge($context, [
                AbstractNormalizer::DEFAULT_CONSTRUCTOR_ARGUMENTS => [
                    Recipient::class => [
                        'email' => $body['recipients'][0]['email'] ?? '',
                        'phone' => $body['recipients'][0]['phone'] ?? '',
                    ],
                ],
            ]));

            return $this->objectNormalizer->denormalize($body, $objectType, $format, [
                AbstractNormalizer::DEFAULT_CONSTRUCTOR_ARGUMENTS => [
                    NotificationTask::class => [
                        'name' => $body['name'],
                        'notification' => $notification,
                        'recipients' => $recipients
                    ],
                ],
            ]);
        }

        if (HttpTask::class === $objectType) {
            return $this->objectNormalizer->denormalize($body, $objectType, $format, [
                AbstractNormalizer::DEFAULT_CONSTRUCTOR_ARGUMENTS => [
                    HttpTask::class => [
                        'name' => $body['name'],
                        'url' => $body['url'],
                        'method' => $body['method'],
                        'clientOptions' => $body['clientOptions'],
                    ],
                ],
            ]);
        }

        return $this->objectNormalizer->denormalize($data, $objectType, $format, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDenormalization($data, string $type, string $format = null): bool
    {
        return \array_key_exists(self::NORMALIZATION_DISCRIMINATOR, $data);
    }

    private function handleDatetimeAttributes(array $body): array
    {
        foreach ($body as $attributeName => $value) {
            if (\in_array($attributeName, self::DATETIME_ATTRIBUTES) && null !== $value) {
                $body[$attributeName] = $this->dateTimeNormalizer->denormalize($value, \DateTimeInterface::class);
            }

            if (\in_array($attributeName, self::DATEINTERVAL_ATTRIBUTES) && null !== $value) {
                $body[$attributeName] = $this->dateIntervalNormalizer->denormalize($value, \DateInterval::class);
            }

            if ('timezone' === $attributeName && null !== $value) {
                $body[$attributeName] = $this->dateTimeZoneNormalizer->denormalize($value, \DateTimeZone::class);
            }
        }

        return $body;
    }

    private function handleDateAttributes(): array
    {
        $dateAttributesCallback = function ($innerObject, $outerObject, string $attributeName, string $format = null, array $context = []) {
            return $innerObject instanceof \DatetimeInterface ? $this->dateTimeNormalizer->normalize($innerObject, $format, $context) : null;
        };

        $dateIntervalAttributesCallback = function ($innerObject, $outerObject, string $attributeName, string $format = null, array $context = []) {
            return $innerObject instanceof \DateInterval ? $this->dateIntervalNormalizer->normalize($innerObject, $format, $context) : null;
        };

        return [
            AbstractNormalizer::CALLBACKS => [
                'arrivalTime' => $dateAttributesCallback,
                'executionAbsoluteDeadline' => $dateIntervalAttributesCallback,
                'executionRelativeDeadline' => $dateIntervalAttributesCallback,
                'executionStartTime' => $dateAttributesCallback,
                'executionEndTime' => $dateAttributesCallback,
                'lastExecution' => $dateAttributesCallback,
                'scheduledAt' => $dateAttributesCallback,
                'timezone' => function ($innerObject, $outerObject, string $attributeName, string $format = null, array $context = []) {
                    return $innerObject instanceof \DateTimeZone ? $this->dateTimeZoneNormalizer->normalize($innerObject, $format, $context) : null;
                },
            ],
        ];
    }
}
