<?php

declare(strict_types=1);

namespace SapB1\Client;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\ResponseInterface;
use SapB1\Events\RequestFailed;
use SapB1\Events\RequestSending;
use SapB1\Events\RequestSent;
use SapB1\Exceptions\ConnectionException;
use SapB1\Exceptions\ServiceLayerException;

class PendingRequest
{
    /**
     * Shared Guzzle client for connection pooling.
     */
    protected static ?Client $sharedClient = null;

    protected ?Client $client = null;

    protected string $baseUrl = '';

    protected string $connection = 'default';

    /**
     * @var array<string, string>
     */
    protected array $headers = [];

    /**
     * @var array<string, mixed>
     */
    protected array $options = [];

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $body = null;

    protected ?ODataBuilder $odata = null;

    protected int $timeout = 30;

    protected int $connectTimeout = 10;

    protected int $retryTimes = 3;

    protected int $retrySleep = 1000;

    protected int $maxRetryDelay = 30000;

    protected bool $useExponentialBackoff = true;

    protected float $jitterFactor = 0.1;

    /**
     * @var array<int, int>
     */
    protected array $retryWhen = [500, 502, 503, 504];

    protected bool $verify = true;

    protected bool $loggingEnabled = false;

    protected ?string $logChannel = null;

    /**
     * Create a new PendingRequest instance.
     */
    public function __construct()
    {
        $this->headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        // Load logging config
        $this->loggingEnabled = (bool) config('sap-b1.logging.enabled', false);
        $this->logChannel = config('sap-b1.logging.channel');

        // Load exponential backoff config
        $this->useExponentialBackoff = (bool) config('sap-b1.http.retry.exponential_backoff', true);
        $this->maxRetryDelay = (int) config('sap-b1.http.retry.max_delay', 30000);
        $this->jitterFactor = (float) config('sap-b1.http.retry.jitter', 0.1);
    }

    /**
     * Set the base URL for requests.
     */
    public function baseUrl(string $url): self
    {
        $this->baseUrl = rtrim($url, '/');

        return $this;
    }

    /**
     * Set the connection name.
     */
    public function connection(string $connection): self
    {
        $this->connection = $connection;

        return $this;
    }

    /**
     * Set headers for the request.
     *
     * @param  array<string, string>  $headers
     */
    public function withHeaders(array $headers): self
    {
        $this->headers = array_merge($this->headers, $headers);

        return $this;
    }

    /**
     * Set a single header.
     */
    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;

        return $this;
    }

    /**
     * Set the session headers.
     *
     * @param  array<string, string>  $headers
     */
    public function withSessionHeaders(array $headers): self
    {
        return $this->withHeaders($headers);
    }

    /**
     * Set the request body.
     *
     * @param  array<string, mixed>  $body
     */
    public function withBody(array $body): self
    {
        $this->body = $body;

        return $this;
    }

    /**
     * Set Guzzle options.
     *
     * @param  array<string, mixed>  $options
     */
    public function withOptions(array $options): self
    {
        $this->options = array_merge($this->options, $options);

        return $this;
    }

    /**
     * Set the OData query builder.
     */
    public function withOData(ODataBuilder $odata): self
    {
        $this->odata = $odata;

        return $this;
    }

    /**
     * Set the request timeout.
     */
    public function timeout(int $seconds): self
    {
        $this->timeout = $seconds;

        return $this;
    }

    /**
     * Set the connection timeout.
     */
    public function connectTimeout(int $seconds): self
    {
        $this->connectTimeout = $seconds;

        return $this;
    }

    /**
     * Configure retry behavior.
     *
     * @param  array<int, int>|null  $when
     */
    public function retry(int $times = 3, int $sleep = 1000, ?array $when = null): self
    {
        $this->retryTimes = $times;
        $this->retrySleep = $sleep;

        if ($when !== null) {
            $this->retryWhen = $when;
        }

        return $this;
    }

    /**
     * Disable retries.
     */
    public function withoutRetry(): self
    {
        $this->retryTimes = 0;

        return $this;
    }

    /**
     * Enable or disable exponential backoff.
     */
    public function exponentialBackoff(bool $enabled = true): self
    {
        $this->useExponentialBackoff = $enabled;

        return $this;
    }

    /**
     * Set the maximum retry delay.
     */
    public function maxRetryDelay(int $milliseconds): self
    {
        $this->maxRetryDelay = $milliseconds;

        return $this;
    }

    /**
     * Set SSL verification.
     */
    public function verify(bool $verify): self
    {
        $this->verify = $verify;

        return $this;
    }

    /**
     * Disable SSL verification.
     */
    public function withoutVerifying(): self
    {
        $this->verify = false;

        return $this;
    }

    /**
     * Enable logging for this request.
     */
    public function withLogging(?string $channel = null): self
    {
        $this->loggingEnabled = true;
        $this->logChannel = $channel ?? $this->logChannel;

        return $this;
    }

    /**
     * Disable logging for this request.
     */
    public function withoutLogging(): self
    {
        $this->loggingEnabled = false;

        return $this;
    }

    /**
     * Send a GET request.
     */
    public function get(string $endpoint): Response
    {
        return $this->send('GET', $endpoint);
    }

    /**
     * Send a POST request.
     *
     * @param  array<string, mixed>  $data
     */
    public function post(string $endpoint, array $data = []): Response
    {
        if (! empty($data)) {
            $this->body = $data;
        }

        return $this->send('POST', $endpoint);
    }

    /**
     * Send a PUT request.
     *
     * @param  array<string, mixed>  $data
     */
    public function put(string $endpoint, array $data = []): Response
    {
        if (! empty($data)) {
            $this->body = $data;
        }

        return $this->send('PUT', $endpoint);
    }

    /**
     * Send a PATCH request.
     *
     * @param  array<string, mixed>  $data
     */
    public function patch(string $endpoint, array $data = []): Response
    {
        if (! empty($data)) {
            $this->body = $data;
        }

        return $this->send('PATCH', $endpoint);
    }

    /**
     * Send a DELETE request.
     */
    public function delete(string $endpoint): Response
    {
        return $this->send('DELETE', $endpoint);
    }

    /**
     * Send the HTTP request.
     */
    protected function send(string $method, string $endpoint): Response
    {
        $url = $this->buildUrl($endpoint);
        $options = $this->buildOptions();

        RequestSending::dispatch(
            $this->connection,
            $method,
            $endpoint,
            $options
        );

        $this->logRequest($method, $endpoint, $options);

        $startTime = microtime(true);
        $attempt = 0;

        while (true) {
            $attempt++;

            try {
                $response = $this->getClient()->request($method, $url, $options);

                $duration = microtime(true) - $startTime;

                RequestSent::dispatch(
                    $this->connection,
                    $method,
                    $endpoint,
                    $response->getStatusCode(),
                    $duration
                );

                $this->logResponse($method, $endpoint, $response->getStatusCode(), $duration);

                return new Response($response);
            } catch (ConnectException $e) {
                if ($attempt >= max(1, $this->retryTimes)) {
                    $this->logError($method, $endpoint, $e);

                    RequestFailed::dispatch(
                        $this->connection,
                        $method,
                        $endpoint,
                        $e
                    );

                    throw new ConnectionException(
                        message: 'Failed to connect to SAP B1: '.$e->getMessage(),
                        context: ['connection' => $this->connection, 'endpoint' => $endpoint]
                    );
                }

                $this->logRetry($method, $endpoint, $attempt, 'ConnectException');
                $this->sleep($attempt);
            } catch (RequestException $e) {
                if ($this->shouldRetry($e->getResponse(), $attempt)) {
                    $this->logRetry($method, $endpoint, $attempt, 'RequestException: '.$e->getResponse()?->getStatusCode());
                    $this->sleep($attempt);

                    continue;
                }

                $this->logError($method, $endpoint, $e);

                RequestFailed::dispatch(
                    $this->connection,
                    $method,
                    $endpoint,
                    $e
                );

                throw $this->createException($e, $endpoint);
            } catch (GuzzleException $e) {
                $this->logError($method, $endpoint, $e);

                RequestFailed::dispatch(
                    $this->connection,
                    $method,
                    $endpoint,
                    $e
                );

                throw new ConnectionException(
                    message: 'Request failed: '.$e->getMessage(),
                    context: ['connection' => $this->connection, 'endpoint' => $endpoint]
                );
            }
        }
    }

    /**
     * Sleep with exponential backoff and jitter.
     */
    protected function sleep(int $attempt): void
    {
        if ($this->useExponentialBackoff) {
            // Exponential backoff: base * 2^attempt
            $delay = min(
                $this->maxRetryDelay,
                $this->retrySleep * (2 ** ($attempt - 1))
            );

            // Add jitter to prevent thundering herd
            $jitter = (int) ($delay * $this->jitterFactor * (mt_rand() / mt_getrandmax()));
            $delay += $jitter;
        } else {
            $delay = $this->retrySleep;
        }

        usleep($delay * 1000);
    }

    /**
     * Log the outgoing request.
     *
     * @param  array<string, mixed>  $options
     */
    protected function logRequest(string $method, string $endpoint, array $options): void
    {
        if (! $this->loggingEnabled) {
            return;
        }

        $context = [
            'connection' => $this->connection,
            'method' => $method,
            'endpoint' => $endpoint,
            'headers' => $this->sanitizeHeaders($options['headers'] ?? []),
        ];

        if (isset($options['json'])) {
            $context['body'] = $this->sanitizeBody($options['json']);
        }

        $this->log('debug', "SAP B1 Request: {$method} {$endpoint}", $context);
    }

    /**
     * Log the response.
     */
    protected function logResponse(string $method, string $endpoint, int $statusCode, float $duration): void
    {
        if (! $this->loggingEnabled) {
            return;
        }

        $this->log('debug', "SAP B1 Response: {$method} {$endpoint}", [
            'connection' => $this->connection,
            'status' => $statusCode,
            'duration_ms' => round($duration * 1000, 2),
        ]);
    }

    /**
     * Log a retry attempt.
     */
    protected function logRetry(string $method, string $endpoint, int $attempt, string $reason): void
    {
        if (! $this->loggingEnabled) {
            return;
        }

        $this->log('warning', "SAP B1 Retry: {$method} {$endpoint}", [
            'connection' => $this->connection,
            'attempt' => $attempt,
            'max_attempts' => $this->retryTimes,
            'reason' => $reason,
        ]);
    }

    /**
     * Log an error.
     */
    protected function logError(string $method, string $endpoint, \Throwable $e): void
    {
        if (! $this->loggingEnabled) {
            return;
        }

        $this->log('error', "SAP B1 Error: {$method} {$endpoint}", [
            'connection' => $this->connection,
            'error' => $e->getMessage(),
            'exception' => get_class($e),
        ]);
    }

    /**
     * Write to log.
     *
     * @param  array<string, mixed>  $context
     */
    protected function log(string $level, string $message, array $context = []): void
    {
        $logger = $this->logChannel ? Log::channel($this->logChannel) : Log::getFacadeRoot();
        $logger->{$level}($message, $context);
    }

    /**
     * Sanitize headers for logging (remove sensitive data).
     *
     * @param  array<string, string>  $headers
     * @return array<string, string>
     */
    protected function sanitizeHeaders(array $headers): array
    {
        $sensitive = ['Cookie', 'Authorization', 'B1SESSION'];

        foreach ($sensitive as $key) {
            if (isset($headers[$key])) {
                $headers[$key] = '[REDACTED]';
            }
        }

        return $headers;
    }

    /**
     * Sanitize body for logging (remove sensitive data).
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    protected function sanitizeBody(array $body): array
    {
        $sensitive = ['Password', 'password', 'UserPassword', 'UserName', 'username'];

        foreach ($sensitive as $key) {
            if (isset($body[$key])) {
                $body[$key] = '[REDACTED]';
            }
        }

        return $body;
    }

    /**
     * Build the full URL.
     */
    protected function buildUrl(string $endpoint): string
    {
        $url = $this->baseUrl.'/'.ltrim($endpoint, '/');

        if ($this->odata !== null) {
            $url .= $this->odata->build();
        }

        return $url;
    }

    /**
     * Build request options.
     *
     * @return array<string, mixed>
     */
    protected function buildOptions(): array
    {
        $options = [
            'headers' => $this->headers,
            'timeout' => $this->timeout,
            'connect_timeout' => $this->connectTimeout,
            'verify' => $this->verify,
            'http_errors' => true,
        ];

        if ($this->body !== null) {
            $options['json'] = $this->body;
        }

        return array_merge($options, $this->options);
    }

    /**
     * Check if request should be retried.
     */
    protected function shouldRetry(?ResponseInterface $response, int $attempt): bool
    {
        if ($attempt >= max(1, $this->retryTimes)) {
            return false;
        }

        if ($response === null) {
            return true;
        }

        return in_array($response->getStatusCode(), $this->retryWhen, true);
    }

    /**
     * Create an appropriate exception from the request exception.
     */
    protected function createException(RequestException $e, string $endpoint): ServiceLayerException
    {
        $response = $e->getResponse();
        $statusCode = $response?->getStatusCode() ?? 0;

        $body = $response?->getBody()->getContents() ?? '';
        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode($body, true);

        /** @var string|null $message */
        $message = $decoded['error']['message']['value']
            ?? $decoded['error']['message']
            ?? $decoded['error']
            ?? $e->getMessage();

        /** @var string|int|null $rawCode */
        $rawCode = $decoded['error']['code'] ?? null;
        $code = $rawCode !== null ? (string) $rawCode : null;

        return new ServiceLayerException(
            message: $message ?? 'Request failed',
            statusCode: $statusCode,
            sapCode: $code,
            context: [
                'connection' => $this->connection,
                'endpoint' => $endpoint,
                'response' => $decoded,
            ]
        );
    }

    /**
     * Get the Guzzle client (with connection pooling).
     */
    protected function getClient(): Client
    {
        // Use instance client if set (for testing)
        if ($this->client !== null) {
            return $this->client;
        }

        // Use shared client for connection pooling
        if (self::$sharedClient === null) {
            $stack = HandlerStack::create();

            self::$sharedClient = new Client([
                'handler' => $stack,
                'headers' => [
                    'Connection' => 'keep-alive',
                ],
                'curl' => [
                    CURLOPT_FORBID_REUSE => false,
                    CURLOPT_FRESH_CONNECT => false,
                    CURLOPT_TCP_KEEPALIVE => 1,
                    CURLOPT_TCP_KEEPIDLE => 60,
                    CURLOPT_TCP_KEEPINTVL => 30,
                ],
            ]);
        }

        return self::$sharedClient;
    }

    /**
     * Set the Guzzle client (for testing).
     */
    public function setClient(Client $client): self
    {
        $this->client = $client;

        return $this;
    }

    /**
     * Reset the shared client (for testing).
     */
    public static function resetSharedClient(): void
    {
        self::$sharedClient = null;
    }

    /**
     * Get the shared client instance (for batch requests).
     */
    public static function getSharedClient(): ?Client
    {
        return self::$sharedClient;
    }

    /**
     * Reset the request state.
     */
    public function reset(): self
    {
        $this->body = null;
        $this->odata = null;
        $this->headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
        $this->options = [];

        return $this;
    }
}
