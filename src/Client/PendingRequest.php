<?php

declare(strict_types=1);

namespace SapB1\Client;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;
use SapB1\Events\RequestFailed;
use SapB1\Events\RequestSending;
use SapB1\Events\RequestSent;
use SapB1\Exceptions\ConnectionException;
use SapB1\Exceptions\ServiceLayerException;

class PendingRequest
{
    protected Client $client;

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

    /**
     * @var array<int, int>
     */
    protected array $retryWhen = [500, 502, 503, 504];

    protected bool $verify = true;

    /**
     * Create a new PendingRequest instance.
     */
    public function __construct()
    {
        $this->headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
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

                return new Response($response);
            } catch (ConnectException $e) {
                if ($attempt >= max(1, $this->retryTimes)) {
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

                usleep($this->retrySleep * 1000);
            } catch (RequestException $e) {
                if ($this->shouldRetry($e->getResponse(), $attempt)) {
                    usleep($this->retrySleep * 1000);

                    continue;
                }

                RequestFailed::dispatch(
                    $this->connection,
                    $method,
                    $endpoint,
                    $e
                );

                throw $this->createException($e, $endpoint);
            } catch (GuzzleException $e) {
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
     * Get the Guzzle client.
     */
    protected function getClient(): Client
    {
        if (! isset($this->client)) {
            $this->client = new Client;
        }

        return $this->client;
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
