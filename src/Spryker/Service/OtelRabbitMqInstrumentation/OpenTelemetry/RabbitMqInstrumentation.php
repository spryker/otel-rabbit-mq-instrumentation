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
use Spryker\Client\RabbitMq\Model\RabbitMqAdapter;
use Spryker\Shared\Opentelemetry\Instrumentation\CachedInstrumentation;
use Spryker\Shared\Opentelemetry\Request\RequestProcessor;
use Throwable;
use function OpenTelemetry\Instrumentation\hook;

class RabbitMqInstrumentation
{
    /**
     * @var string
     */
    protected const METHOD_NAME = 'sendMessage';

    /**
     * @return void
     */
    public static function register(): void
    {
        // phpcs:disable
        hook(
            class: RabbitMqAdapter::class,
            function: static::METHOD_NAME,
            pre: function (array $params): void {
                $instrumentation = new CachedInstrumentation();
                $request = new RequestProcessor();

                $span = $instrumentation::getCachedInstrumentation()
                    ->tracer()
                    ->spanBuilder('rabbitmq-send-message')
//                    ->setSpanKind(SpanKind::KIND_CLIENT)
//                    ->setAttribute(TraceAttributes::URL_FULL, $request->getRequest()->getUri())
//                    ->setAttribute(TraceAttributes::HTTP_REQUEST_METHOD, $request->getRequest()->getMethod())
//                    ->setAttribute(TraceAttributes::URL_QUERY, $request->getRequest()->getQueryString())
//                    ->setAttribute(TraceAttributes::URL_DOMAIN, $request->getRequest()->headers->get('host'))
                    ->startSpan();
                $span->activate();

                Context::storage()->attach($span->storeInContext(Context::getCurrent()));
            },
            post: function ($instance, array $params, $response, ?Throwable $exception): void {
                $span = Span::fromContext(Context::getCurrent());

                if ($exception !== null) {
                    $span->recordException($exception);
                    $span->setStatus(StatusCode::STATUS_ERROR);
                } else {
                    $span->setAttribute('queryTime', $response->getQueryTime());
                    $span->setStatus(StatusCode::STATUS_OK);
                }

                $span->end();
            }
        );
        // phpcs:enable
    }
}
