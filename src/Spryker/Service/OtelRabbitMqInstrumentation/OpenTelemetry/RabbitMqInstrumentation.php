<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Service\OtelRabbitMqInstrumentation\OpenTelemetry;

use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Context;
use OpenTelemetry\SemConv\TraceAttributes;
use Spryker\Client\Queue\QueueClient;
use Spryker\Client\RabbitMq\Model\RabbitMqAdapter;
use Spryker\Shared\Opentelemetry\Instrumentation\CachedInstrumentation;
use Spryker\Shared\Opentelemetry\Request\RequestProcessor;
use Spryker\Zed\Queue\Business\QueueFacade;
use Throwable;
use function OpenTelemetry\Instrumentation\hook;

class RabbitMqInstrumentation
{
    /**
     * @return void
     */
    public static function register(): void
    {
        // phpcs:disable
        hook(
            class: RabbitMqAdapter::class,
            function: 'sendMessage',
            pre: function (RabbitMqAdapter $rabbitMqAdapter, array $params): void {
                $instrumentation = new CachedInstrumentation();
                $request = new RequestProcessor();
                $startTime = microtime(true);

                $span = $instrumentation::getCachedInstrumentation()
                    ->tracer()
                    ->spanBuilder('rabbitmq-sendMessage')
                    ->setAttribute('start_time', $startTime)
                    ->setSpanKind(SpanKind::KIND_CLIENT)
                    ->setAttribute(TraceAttributes::HTTP_REQUEST_METHOD, $request->getRequest()->getMethod())
                    ->setAttribute('queue.name', $params[0]);
                if(
                    array_key_exists(1, $params)
                    && array_key_exists(0, $params[1])
                    && is_string($params[1][0])
                ) {
                    $array = json_decode($params[1][0]->getBody(), true);
                    if (array_key_exists('eventName', $array)) {
                        $span->setAttribute(TraceAttributes::EVENT_NAME, $array['eventName']);
                    }
                    if (array_key_exists('listenerClassName', $array)) {
                        $span->setAttribute('event.listenerClassName', $array['listenerClassName']);
                    }
                    if (array_key_exists('transferClassName', $array)) {
                        $span->setAttribute('event.transferClassName', $array['eventName']);
                    }
                }

                $span->setAttribute(TraceAttributes::URL_DOMAIN, $request->getRequest()->headers->get('host'))
                    ->startSpan()
                    ->activate();

                Context::storage()->attach($span->storeInContext(Context::getCurrent()));
            },
            post: function ($instance, array $params, $response, ?Throwable $exception): void {
                $span = Span::fromContext(Context::getCurrent());

                if ($exception !== null) {
                    $span->recordException($exception);
                    $span->setStatus(StatusCode::STATUS_ERROR);
                } else {
                    $span->setStatus(StatusCode::STATUS_OK);
                }
                $endTime = microtime(true);
                $duration = $endTime - $span->getAttribute('start_time');
                $span->setAttribute('start_time', null);
                $span->setAttribute('duration', $duration);

                $span->end();
            }
        );
        hook(
            class: RabbitMqAdapter::class,
            function: 'sendMessages',
            pre: function (RabbitMqAdapter $rabbitMqAdapter, array $params): void {
                $instrumentation = new CachedInstrumentation();
                $request = new RequestProcessor();
                $startTime = microtime(true);

                $span = $instrumentation::getCachedInstrumentation()
                    ->tracer()
                    ->spanBuilder('rabbitmq-sendMessages')
                    ->setAttribute('start_time', $startTime)
                    ->setSpanKind(SpanKind::KIND_CLIENT)
                    ->setAttribute(TraceAttributes::HTTP_REQUEST_METHOD, $request->getRequest()->getMethod())
                    ->setAttribute('queue.name', $params[0]);
                if(
                    array_key_exists(1, $params)
                    && array_key_exists(0, $params[1])
                    && is_string($params[1][0])
                ) {
                    $array = json_decode($params[1][0]->getBody(), true);
                        if (array_key_exists('eventName', $array)) {
                            $span->setAttribute(TraceAttributes::EVENT_NAME, $array['eventName']);
                        }
                        if (array_key_exists('listenerClassName', $array)) {
                            $span->setAttribute('event.listenerClassName', $array['listenerClassName']);
                        }
                        if (array_key_exists('transferClassName', $array)) {
                            $span->setAttribute('event.transferClassName', $array['eventName']);
                        }
                }

                    $span->setAttribute(TraceAttributes::URL_DOMAIN, $request->getRequest()->headers->get('host'))
                    ->startSpan()
                    ->activate();

                Context::storage()->attach($span->storeInContext(Context::getCurrent()));
            },
            post: function ($instance, array $params, $response, ?Throwable $exception): void {
                $span = Span::fromContext(Context::getCurrent());

                if ($exception !== null) {
                    $span->recordException($exception);
                    $span->setStatus(StatusCode::STATUS_ERROR);
                } else {
                    $span->setStatus(StatusCode::STATUS_OK);
                }
                $endTime = microtime(true);
                $duration = $endTime - $span->getAttribute('start_time');
                $span->setAttribute('start_time', null);
                $span->setAttribute('duration', $duration);

                $span->end();
            }
        );

//        hook(
//            class: QueueFacade::class,
//            function: 'startWorker',
//            pre: function (QueueFacade $queueFacade, array $params): void {
//                $instrumentation = new CachedInstrumentation();
//                $request = new RequestProcessor();
//                $startTime = microtime(true);
//
//                $span = $instrumentation::getCachedInstrumentation()
//                    ->tracer()
//                    ->spanBuilder('rabbitmq-startWorker')
//                    ->setSpanKind(SpanKind::KIND_CLIENT)
//                    ->setAttribute(TraceAttributes::PROCESS_COMMAND, $params[0])
//                    ->setAttribute('start_time', $startTime)
//                    ->startSpan();
//                $span->activate();
//
//                Context::storage()->attach($span->storeInContext(Context::getCurrent()));
//            },
//            post: function ($instance, array $params, $response, ?Throwable $exception): void {
//                $span = Span::fromContext(Context::getCurrent());
//
//                if ($exception !== null) {
//                    $span->recordException($exception);
//                    $span->setStatus(StatusCode::STATUS_ERROR);
//                } else {
//                    $span->setStatus(StatusCode::STATUS_OK);
//                }
//                $endTime = microtime(true);
//                $duration = $endTime - $span->getAttribute('start_time');
//                $span->setAttribute('start_time', null);
//                $span->setAttribute('duration', $duration);
//
//                $span->end();
//            }
//        );
        // phpcs:enable
    }
}
