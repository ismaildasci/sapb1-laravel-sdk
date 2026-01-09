<?php

declare(strict_types=1);

namespace SapB1\Telemetry;

use SapB1\Client\SapB1Client;

class TelemetryService
{
    protected bool $enabled = false;

    protected static ?TelemetryService $instance = null;

    /**
     * Get singleton instance.
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    /**
     * Enable OpenTelemetry integration.
     */
    public function enable(): self
    {
        if (! $this->isOpenTelemetryAvailable()) {
            return $this;
        }

        $this->enabled = true;

        // Register middleware globally
        SapB1Client::pushMiddleware(new OpenTelemetryMiddleware);

        return $this;
    }

    /**
     * Disable OpenTelemetry integration.
     */
    public function disable(): self
    {
        $this->enabled = false;

        // Remove middleware
        SapB1Client::removeMiddleware(OpenTelemetryMiddleware::class);

        return $this;
    }

    /**
     * Check if telemetry is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Check if OpenTelemetry packages are available.
     */
    public function isOpenTelemetryAvailable(): bool
    {
        return interface_exists(\OpenTelemetry\API\Trace\TracerInterface::class);
    }

    /**
     * Record a custom metric.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function recordMetric(string $name, float $value, array $attributes = []): void
    {
        if (! $this->enabled || ! $this->isOpenTelemetryAvailable()) {
            return;
        }

        try {
            // @phpstan-ignore-next-line - OpenTelemetry is an optional dependency
            $meter = \OpenTelemetry\API\Globals::meterProvider()
                ->getMeter('sapb1-laravel-sdk');

            $counter = $meter->createCounter($name);
            $counter->add($value, $attributes);
        } catch (\Throwable) {
            // Silently fail if metrics not configured
        }
    }

    /**
     * Record request count metric.
     */
    public function recordRequest(string $endpoint, string $method, bool $success): void
    {
        $this->recordMetric('sap_b1_requests_total', 1, [
            'endpoint' => $endpoint,
            'method' => $method,
            'success' => $success ? 'true' : 'false',
        ]);
    }

    /**
     * Record request duration metric.
     */
    public function recordDuration(string $endpoint, float $durationMs): void
    {
        $this->recordMetric('sap_b1_request_duration_ms', $durationMs, [
            'endpoint' => $endpoint,
        ]);
    }

    /**
     * Record error metric.
     */
    public function recordError(string $endpoint, string $errorType): void
    {
        $this->recordMetric('sap_b1_errors_total', 1, [
            'endpoint' => $endpoint,
            'error_type' => $errorType,
        ]);
    }
}
