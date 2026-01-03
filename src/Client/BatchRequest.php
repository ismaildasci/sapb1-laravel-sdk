<?php

declare(strict_types=1);

namespace SapB1\Client;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Str;
use SapB1\Exceptions\BatchException;
use SapB1\Exceptions\ConnectionException;
use SapB1\Session\SessionData;

class BatchRequest
{
    protected string $boundary;

    protected string $changesetBoundary;

    /**
     * @var array<int, array{method: string, endpoint: string, body: array<string, mixed>|null, inChangeset: bool}>
     */
    protected array $requests = [];

    protected bool $inChangeset = false;

    protected string $baseUrl;

    protected SessionData $session;

    protected ?Client $client = null;

    protected int $timeout = 60;

    protected bool $verify = true;

    /**
     * Create a new BatchRequest instance.
     */
    public function __construct(string $baseUrl, SessionData $session)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->session = $session;
        $this->boundary = 'batch_'.Str::uuid()->toString();
        $this->changesetBoundary = 'changeset_'.Str::uuid()->toString();
    }

    /**
     * Add a GET request to the batch.
     */
    public function get(string $endpoint): self
    {
        $this->requests[] = [
            'method' => 'GET',
            'endpoint' => $endpoint,
            'body' => null,
            'inChangeset' => false, // GET requests cannot be in changeset
        ];

        return $this;
    }

    /**
     * Add a POST request to the batch.
     *
     * @param  array<string, mixed>  $data
     */
    public function post(string $endpoint, array $data = []): self
    {
        $this->requests[] = [
            'method' => 'POST',
            'endpoint' => $endpoint,
            'body' => $data,
            'inChangeset' => $this->inChangeset,
        ];

        return $this;
    }

    /**
     * Add a PATCH request to the batch.
     *
     * @param  array<string, mixed>  $data
     */
    public function patch(string $endpoint, array $data = []): self
    {
        $this->requests[] = [
            'method' => 'PATCH',
            'endpoint' => $endpoint,
            'body' => $data,
            'inChangeset' => $this->inChangeset,
        ];

        return $this;
    }

    /**
     * Add a PUT request to the batch.
     *
     * @param  array<string, mixed>  $data
     */
    public function put(string $endpoint, array $data = []): self
    {
        $this->requests[] = [
            'method' => 'PUT',
            'endpoint' => $endpoint,
            'body' => $data,
            'inChangeset' => $this->inChangeset,
        ];

        return $this;
    }

    /**
     * Add a DELETE request to the batch.
     */
    public function delete(string $endpoint): self
    {
        $this->requests[] = [
            'method' => 'DELETE',
            'endpoint' => $endpoint,
            'body' => null,
            'inChangeset' => $this->inChangeset,
        ];

        return $this;
    }

    /**
     * Start a changeset (atomic unit of work).
     * All requests added after this will be part of the changeset
     * until endChangeset() is called.
     */
    public function beginChangeset(): self
    {
        $this->inChangeset = true;
        $this->changesetBoundary = 'changeset_'.Str::uuid()->toString();

        return $this;
    }

    /**
     * End the current changeset.
     */
    public function endChangeset(): self
    {
        $this->inChangeset = false;

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
     * Set SSL verification.
     */
    public function verify(bool $verify): self
    {
        $this->verify = $verify;

        return $this;
    }

    /**
     * Set a custom Guzzle client (for testing).
     */
    public function setClient(Client $client): self
    {
        $this->client = $client;

        return $this;
    }

    /**
     * Execute the batch request.
     *
     * @throws BatchException
     * @throws ConnectionException
     */
    public function execute(): BatchResponse
    {
        if (empty($this->requests)) {
            throw new BatchException('No requests added to batch');
        }

        $body = $this->buildBody();

        try {
            $response = $this->getClient()->request('POST', $this->baseUrl.'/$batch', [
                'headers' => [
                    'Content-Type' => 'multipart/mixed; boundary='.$this->boundary,
                    'Accept' => 'application/json',
                    ...$this->session->getHeaders(),
                ],
                'body' => $body,
                'timeout' => $this->timeout,
                'verify' => $this->verify,
                'http_errors' => false,
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode >= 400) {
                $responseBody = $response->getBody()->getContents();
                throw new BatchException(
                    "Batch request failed with status {$statusCode}: {$responseBody}",
                    $statusCode
                );
            }

            return new BatchResponse(
                $response->getBody()->getContents(),
                $this->boundary
            );
        } catch (GuzzleException $e) {
            throw new ConnectionException(
                message: 'Batch request failed: '.$e->getMessage(),
                context: ['requests_count' => count($this->requests)]
            );
        }
    }

    /**
     * Build the multipart request body.
     */
    protected function buildBody(): string
    {
        $parts = [];

        // Group requests: changesets together, GET requests separate
        $changesetRequests = [];
        $getRequests = [];

        foreach ($this->requests as $request) {
            if ($request['method'] === 'GET') {
                $getRequests[] = $request;
            } elseif ($request['inChangeset']) {
                $changesetRequests[] = $request;
            } else {
                // Non-changeset write operations go directly in batch
                $parts[] = $this->buildRequestPart($request);
            }
        }

        // Add GET requests (not in changeset)
        foreach ($getRequests as $request) {
            $parts[] = $this->buildRequestPart($request);
        }

        // Add changeset if there are requests
        if (! empty($changesetRequests)) {
            $parts[] = $this->buildChangeset($changesetRequests);
        }

        $body = '';
        foreach ($parts as $part) {
            $body .= "--{$this->boundary}\r\n";
            $body .= $part;
            $body .= "\r\n";
        }
        $body .= "--{$this->boundary}--\r\n";

        return $body;
    }

    /**
     * Build a single request part.
     *
     * @param  array{method: string, endpoint: string, body: array<string, mixed>|null, inChangeset: bool}  $request
     */
    protected function buildRequestPart(array $request): string
    {
        $part = "Content-Type: application/http\r\n";
        $part .= "Content-Transfer-Encoding: binary\r\n\r\n";

        $endpoint = ltrim($request['endpoint'], '/');
        $part .= "{$request['method']} {$endpoint} HTTP/1.1\r\n";
        $part .= "Content-Type: application/json\r\n";

        if ($request['body'] !== null) {
            $jsonBody = json_encode($request['body'], JSON_UNESCAPED_UNICODE);
            if ($jsonBody === false) {
                $jsonBody = '{}';
            }
            $part .= 'Content-Length: '.strlen($jsonBody)."\r\n\r\n";
            $part .= $jsonBody;
        } else {
            $part .= "\r\n";
        }

        return $part;
    }

    /**
     * Build a changeset (atomic group of requests).
     *
     * @param  array<int, array{method: string, endpoint: string, body: array<string, mixed>|null, inChangeset: bool}>  $requests
     */
    protected function buildChangeset(array $requests): string
    {
        $changeset = "Content-Type: multipart/mixed; boundary={$this->changesetBoundary}\r\n\r\n";

        foreach ($requests as $request) {
            $changeset .= "--{$this->changesetBoundary}\r\n";
            $changeset .= $this->buildRequestPart($request);
            $changeset .= "\r\n";
        }

        $changeset .= "--{$this->changesetBoundary}--";

        return $changeset;
    }

    /**
     * Get the Guzzle client.
     */
    protected function getClient(): Client
    {
        if ($this->client !== null) {
            return $this->client;
        }

        return PendingRequest::getSharedClient() ?? new Client;
    }

    /**
     * Get the number of requests in the batch.
     */
    public function count(): int
    {
        return count($this->requests);
    }

    /**
     * Check if the batch is empty.
     */
    public function isEmpty(): bool
    {
        return empty($this->requests);
    }

    /**
     * Clear all requests from the batch.
     */
    public function clear(): self
    {
        $this->requests = [];
        $this->inChangeset = false;

        return $this;
    }
}
