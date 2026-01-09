<?php

declare(strict_types=1);

namespace SapB1\Telemetry;

use Closure;
use SapB1\Client\PendingRequest;
use SapB1\Client\Response;
use SapB1\Contracts\MiddlewareInterface;

/**
 * OpenTelemetry middleware for distributed tracing.
 *
 * Requires: open-telemetry/sdk, open-telemetry/api
 */
class OpenTelemetryMiddleware implements MiddlewareInterface
{
    protected bool $enabled = false;

    protected mixed $tracer = null;

    public function __construct()
    {
        $this->enabled = $this->isOpenTelemetryAvailable();

        if ($this->enabled) {
            $this->initializeTracer();
        }
    }

    public function handle(PendingRequest $request, Closure $next): Response
    {
        if (! $this->enabled || $this->tracer === null) {
            return $next($request);
        }

        $spanName = sprintf('SAP B1 %s %s', $request->getMethod(), $request->getEndpoint());

        // Create span using OpenTelemetry API
        /** @var object $tracer */
        $tracer = $this->tracer;

        // @phpstan-ignore-next-line - OpenTelemetry is an optional dependency
        $spanBuilder = $tracer->spanBuilder($spanName)
            ->setSpanKind(\OpenTelemetry\API\Trace\SpanKind::KIND_CLIENT); // @phpstan-ignore-line

        $span = $spanBuilder->startSpan();
        $scope = $span->activate();

        try {
            // Set span attributes
            $span->setAttribute('sap.method', $request->getMethod());
            $span->setAttribute('sap.endpoint', $request->getEndpoint());
            $span->setAttribute('http.method', $request->getMethod());
            $span->setAttribute('http.url', $request->getEndpoint());

            if ($request->getRequestId()) {
                $span->setAttribute('sap.request_id', $request->getRequestId());
            }

            $response = $next($request);

            // Record response attributes
            $span->setAttribute('http.status_code', $response->status());
            $span->setAttribute('sap.success', $response->successful());

            if ($response->failed()) {
                $span->setAttribute('sap.error', $response->errorMessage() ?? 'Unknown error');
                // @phpstan-ignore-next-line - OpenTelemetry is an optional dependency
                $span->setStatus(\OpenTelemetry\API\Trace\StatusCode::STATUS_ERROR);
            } else {
                // @phpstan-ignore-next-line - OpenTelemetry is an optional dependency
                $span->setStatus(\OpenTelemetry\API\Trace\StatusCode::STATUS_OK);
            }

            return $response;
        } catch (\Throwable $e) {
            $span->recordException($e);
            // @phpstan-ignore-next-line - OpenTelemetry is an optional dependency
            $span->setStatus(\OpenTelemetry\API\Trace\StatusCode::STATUS_ERROR, $e->getMessage());

            throw $e;
        } finally {
            $span->end();
            $scope->detach();
        }
    }

    /**
     * Check if OpenTelemetry is available.
     */
    protected function isOpenTelemetryAvailable(): bool
    {
        return interface_exists(\OpenTelemetry\API\Trace\TracerInterface::class)
            && class_exists(\OpenTelemetry\API\Globals::class);
    }

    /**
     * Initialize the OpenTelemetry tracer.
     */
    protected function initializeTracer(): void
    {
        try {
            // @phpstan-ignore-next-line - OpenTelemetry is an optional dependency
            $this->tracer = \OpenTelemetry\API\Globals::tracerProvider()
                ->getTracer('sapb1-laravel-sdk', '1.7.0');
        } catch (\Throwable) {
            $this->enabled = false;
        }
    }

    /**
     * Check if telemetry is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}
