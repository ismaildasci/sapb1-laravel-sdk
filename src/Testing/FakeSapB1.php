<?php

declare(strict_types=1);

namespace SapB1\Testing;

use Closure;
use PHPUnit\Framework\Assert;
use SapB1\Client\Response;
use SapB1\Client\SapB1Client;

class FakeSapB1
{
    /**
     * @var array<string, Response|Closure>
     */
    protected array $responses = [];

    /**
     * @var array<int, array{method: string, endpoint: string, data: array<string, mixed>|null}>
     */
    protected array $recorded = [];

    protected ?Response $defaultResponse = null;

    protected bool $preventStrayRequests = false;

    /**
     * Create a new FakeSapB1 instance and bind it to the container.
     *
     * @param  array<string, Response|Closure|array<string, mixed>>|Closure|null  $responses
     */
    public static function fake(array|Closure|null $responses = null): self
    {
        $fake = new self;

        if ($responses !== null) {
            $fake->stub($responses);
        }

        $fake->swap();

        return $fake;
    }

    /**
     * Stub responses for specific endpoints.
     *
     * @param  array<string, Response|Closure|array<string, mixed>>|Closure  $responses
     */
    public function stub(array|Closure $responses): self
    {
        if ($responses instanceof Closure) {
            $this->defaultResponse = null;
            $this->responses['*'] = $responses;

            return $this;
        }

        foreach ($responses as $pattern => $response) {
            if ($response instanceof Response) {
                $this->responses[$pattern] = $response;
            } elseif ($response instanceof Closure) {
                $this->responses[$pattern] = $response;
            } else {
                $this->responses[$pattern] = FakeResponse::make($response);
            }
        }

        return $this;
    }

    /**
     * Set a default response for unmatched requests.
     */
    public function defaultResponse(Response $response): self
    {
        $this->defaultResponse = $response;

        return $this;
    }

    /**
     * Prevent stray requests (throw exception for unmatched).
     */
    public function preventStrayRequests(): self
    {
        $this->preventStrayRequests = true;

        return $this;
    }

    /**
     * Allow stray requests.
     */
    public function allowStrayRequests(): self
    {
        $this->preventStrayRequests = false;

        return $this;
    }

    /**
     * Record a request.
     *
     * @param  array<string, mixed>|null  $data
     */
    public function record(string $method, string $endpoint, ?array $data = null): void
    {
        $this->recorded[] = [
            'method' => $method,
            'endpoint' => $endpoint,
            'data' => $data,
        ];
    }

    /**
     * Get the response for an endpoint.
     */
    public function getResponse(string $method, string $endpoint): Response
    {
        $key = "{$method} {$endpoint}";

        // Check exact match first
        if (isset($this->responses[$key])) {
            return $this->resolveResponse($this->responses[$key], $method, $endpoint);
        }

        // Check endpoint only
        if (isset($this->responses[$endpoint])) {
            return $this->resolveResponse($this->responses[$endpoint], $method, $endpoint);
        }

        // Check pattern matches
        foreach ($this->responses as $pattern => $response) {
            if ($this->matchesPattern($pattern, $key) || $this->matchesPattern($pattern, $endpoint)) {
                return $this->resolveResponse($response, $method, $endpoint);
            }
        }

        // Check wildcard
        if (isset($this->responses['*'])) {
            return $this->resolveResponse($this->responses['*'], $method, $endpoint);
        }

        if ($this->preventStrayRequests) {
            throw new \RuntimeException("No fake response defined for [{$method} {$endpoint}]");
        }

        return $this->defaultResponse ?? FakeResponse::make();
    }

    /**
     * Resolve a response from a value or closure.
     */
    protected function resolveResponse(Response|Closure $response, string $method, string $endpoint): Response
    {
        if ($response instanceof Closure) {
            $result = $response($method, $endpoint);

            if ($result instanceof Response) {
                return $result;
            }

            if (is_array($result)) {
                return FakeResponse::make($result);
            }

            return FakeResponse::make();
        }

        return $response;
    }

    /**
     * Check if a pattern matches a value.
     */
    protected function matchesPattern(string $pattern, string $value): bool
    {
        if ($pattern === '*') {
            return true;
        }

        // Convert wildcard pattern to regex
        $regex = '/^'.str_replace(['\*', '\?'], ['.*', '.'], preg_quote($pattern, '/')).'$/i';

        return (bool) preg_match($regex, $value);
    }

    /**
     * Swap the SapB1Client in the container with a fake.
     */
    public function swap(): self
    {
        $fake = $this;

        app()->singleton(SapB1Client::class, function () use ($fake): FakeSapB1Client {
            return new FakeSapB1Client($fake);
        });

        return $this;
    }

    /**
     * Assert that a request was sent.
     */
    public function assertSent(string $endpoint, ?Closure $callback = null): self
    {
        $found = false;

        foreach ($this->recorded as $request) {
            if ($this->matchesPattern($endpoint, $request['endpoint'])) {
                if ($callback === null || $callback($request)) {
                    $found = true;
                    break;
                }
            }
        }

        Assert::assertTrue($found, "Expected request to [{$endpoint}] was not sent.");

        return $this;
    }

    /**
     * Assert that a request was not sent.
     */
    public function assertNotSent(string $endpoint, ?Closure $callback = null): self
    {
        $found = false;

        foreach ($this->recorded as $request) {
            if ($this->matchesPattern($endpoint, $request['endpoint'])) {
                if ($callback === null || $callback($request)) {
                    $found = true;
                    break;
                }
            }
        }

        Assert::assertFalse($found, "Unexpected request to [{$endpoint}] was sent.");

        return $this;
    }

    /**
     * Assert that no requests were sent.
     */
    public function assertNothingSent(): self
    {
        Assert::assertEmpty($this->recorded, 'Requests were sent unexpectedly.');

        return $this;
    }

    /**
     * Assert the number of requests sent.
     */
    public function assertSentCount(int $count): self
    {
        Assert::assertCount($count, $this->recorded);

        return $this;
    }

    /**
     * Assert a GET request was sent.
     */
    public function assertGet(string $endpoint): self
    {
        return $this->assertMethodSent('GET', $endpoint);
    }

    /**
     * Assert a POST request was sent.
     *
     * @param  array<string, mixed>|null  $data
     */
    public function assertPost(string $endpoint, ?array $data = null): self
    {
        return $this->assertMethodSent('POST', $endpoint, $data);
    }

    /**
     * Assert a PATCH request was sent.
     *
     * @param  array<string, mixed>|null  $data
     */
    public function assertPatch(string $endpoint, ?array $data = null): self
    {
        return $this->assertMethodSent('PATCH', $endpoint, $data);
    }

    /**
     * Assert a DELETE request was sent.
     */
    public function assertDelete(string $endpoint): self
    {
        return $this->assertMethodSent('DELETE', $endpoint);
    }

    /**
     * Assert a specific method request was sent.
     *
     * @param  array<string, mixed>|null  $data
     */
    protected function assertMethodSent(string $method, string $endpoint, ?array $data = null): self
    {
        $found = false;

        foreach ($this->recorded as $request) {
            if ($request['method'] === $method && $this->matchesPattern($endpoint, $request['endpoint'])) {
                if ($data === null || $this->dataMatches($data, $request['data'] ?? [])) {
                    $found = true;
                    break;
                }
            }
        }

        Assert::assertTrue($found, "Expected {$method} request to [{$endpoint}] was not sent.");

        return $this;
    }

    /**
     * Check if data matches expected values.
     *
     * @param  array<string, mixed>  $expected
     * @param  array<string, mixed>  $actual
     */
    protected function dataMatches(array $expected, array $actual): bool
    {
        foreach ($expected as $key => $value) {
            if (! isset($actual[$key]) || $actual[$key] !== $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get all recorded requests.
     *
     * @return array<int, array{method: string, endpoint: string, data: array<string, mixed>|null}>
     */
    public function recorded(): array
    {
        return $this->recorded;
    }

    /**
     * Clear recorded requests.
     */
    public function clearRecorded(): self
    {
        $this->recorded = [];

        return $this;
    }
}
