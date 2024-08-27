<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Service\OtelRabbitMqInstrumentation\OpenTelemetry;

use Generated\Shared\Transfer\EventQueueSendMessageBodyTransfer;
use Generated\Shared\Transfer\QueueSendMessageTransfer;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Context;
use OpenTelemetry\SemConv\TraceAttributes;
use Spryker\Client\RabbitMq\Model\RabbitMqAdapter;
use Spryker\Shared\Opentelemetry\Instrumentation\CachedInstrumentation;
use Spryker\Shared\Opentelemetry\Request\RequestProcessor;
use Spryker\Zed\Opentelemetry\Business\Generator\SpanFilter\SamplerSpanFilter;
use Throwable;
use function OpenTelemetry\Instrumentation\hook;

class RabbitMqInstrumentation
{
    /**
     * @var string
     */
    protected const ATTRIBUTE_QUEUE_NAME = 'queue.name';

    /**
     * @var string
     */
    protected const HEADER_HOST = 'host';

    /**
     * @var string
     */
    protected const ATTRIBUTE_EVENT_LISTENER_CLASS_NAME = 'event.listenerClassName';

    /**
     * @var string
     */
    protected const SPAN_NAME_SEND_MESSAGES = 'rabbitmq-sendMessages';

    /**
     * @var string
     */
    protected const FUNCTION_SEND_MESSAGES = 'sendMessages';

    /**
     * @var string
     */
    protected const SPAN_NAME_SEND_MESSAGE = 'rabbitmq-sendMessage';

    /**
     * @var string
     */
    protected const FUNCTION_SEND_MESSAGE = 'sendMessage';

    /**
     * @return void
     */
    public static function register(): void
    {
        $functions = [
            static::FUNCTION_SEND_MESSAGE => static::SPAN_NAME_SEND_MESSAGE,
            static::FUNCTION_SEND_MESSAGES => static::SPAN_NAME_SEND_MESSAGES,
        ];

        foreach ($functions as $function => $spanName) {
            static::registerHook($function, $spanName);
        }
    }

    /**
     * @param string $functionName
     * @param string $spanName
     *
     * @return void
     */
    protected static function registerHook(string $functionName, string $spanName): void
    {
        $instrumentation = CachedInstrumentation::getCachedInstrumentation();
        $request = (new RequestProcessor())->getRequest();

        hook(
            class: RabbitMqAdapter::class,
            function: $functionName,
            pre: function (RabbitMqAdapter $rabbitMqAdapter, array $params) use ($instrumentation, $spanName, $request): void {

                $span = $instrumentation->tracer()
                    ->spanBuilder($spanName)
                    ->setSpanKind(SpanKind::KIND_CLIENT)
                    ->setAttribute(TraceAttributes::HTTP_REQUEST_METHOD, $request->getMethod())
                    ->setAttribute(static::ATTRIBUTE_QUEUE_NAME, $params[0]);

                if (static::isValidMessage($params)) {
                    $eventQueueSendMessageBodyArray = json_decode($params[1][0]->getBody(), true);
                    if (array_key_exists(EventQueueSendMessageBodyTransfer::EVENT_NAME, $eventQueueSendMessageBodyArray)) {
                        $span->setAttribute(TraceAttributes::EVENT_NAME, $eventQueueSendMessageBodyArray[EventQueueSendMessageBodyTransfer::EVENT_NAME]);
                    }
                    if (array_key_exists(EventQueueSendMessageBodyTransfer::LISTENER_CLASS_NAME, $eventQueueSendMessageBodyArray)) {
                        $span->setAttribute(static::ATTRIBUTE_EVENT_LISTENER_CLASS_NAME, $eventQueueSendMessageBodyArray[EventQueueSendMessageBodyTransfer::LISTENER_CLASS_NAME]);
                    }
                }

                $span
                    ->setAttribute(TraceAttributes::URL_DOMAIN, $request->headers->get(static::HEADER_HOST))
                    ->startSpan()
                    ->activate();
            },
            post: function (RabbitMqAdapter $rabbitMqAdapter, array $params, $response, ?Throwable $exception): void {
                $span = Span::fromContext(Context::getCurrent());

                if ($exception !== null) {
                    $span->recordException($exception);
                    $span->setStatus(StatusCode::STATUS_ERROR);
                } else {
                    $span->setStatus(StatusCode::STATUS_OK);
                }

                $span->end();
            }
        );
    }

    /**
     * @param array<int, mixed> $params
     *
     * @return bool
     */
    protected static function isValidMessage(array $params): bool
    {
        $queueSendMessageTransfer = $params[1][0] ?? null;

        if ($queueSendMessageTransfer === null) {
            return false;
        }

        return $queueSendMessageTransfer instanceof QueueSendMessageTransfer
            && is_string($queueSendMessageTransfer->getBody());
    }
}
