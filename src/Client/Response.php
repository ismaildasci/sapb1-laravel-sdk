<?php

declare(strict_types=1);

namespace SapB1\Client;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use JsonSerializable;
use Psr\Http\Message\ResponseInterface;
use SapB1\Exceptions\JsonDecodeException;

/**
 * @implements Arrayable<string, mixed>
 */
readonly class Response implements Arrayable, Jsonable, JsonSerializable
{
    /**
     * @var array<string, mixed>|null
     */
    protected ?array $decoded;

    public function __construct(
        protected ResponseInterface $response
    ) {
        $this->decoded = $this->decodeBody();
    }

    /**
     * Get the response status code.
     */
    public function status(): int
    {
        return $this->response->getStatusCode();
    }

    /**
     * Check if the response was successful.
     */
    public function successful(): bool
    {
        return $this->status() >= 200 && $this->status() < 300;
    }

    /**
     * Check if the response was a redirect.
     */
    public function redirect(): bool
    {
        return $this->status() >= 300 && $this->status() < 400;
    }

    /**
     * Check if the response indicates a client error.
     */
    public function clientError(): bool
    {
        return $this->status() >= 400 && $this->status() < 500;
    }

    /**
     * Check if the response indicates a server error.
     */
    public function serverError(): bool
    {
        return $this->status() >= 500;
    }

    /**
     * Check if the response failed.
     */
    public function failed(): bool
    {
        return $this->serverError() || $this->clientError();
    }

    /**
     * Get the raw body of the response.
     */
    public function body(): string
    {
        return (string) $this->response->getBody();
    }

    /**
     * Get the JSON decoded body of the response.
     */
    public function json(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->decoded;
        }

        return data_get($this->decoded, $key, $default);
    }

    /**
     * Get a header from the response.
     */
    public function header(string $header): ?string
    {
        $values = $this->response->getHeader($header);

        return $values[0] ?? null;
    }

    /**
     * Get all headers from the response.
     *
     * @return array<string, array<string>>
     */
    public function headers(): array
    {
        return $this->response->getHeaders();
    }

    /**
     * Get the OData value array from the response.
     *
     * @return array<int, array<string, mixed>>
     */
    public function value(): array
    {
        /** @var array<int, array<string, mixed>> $value */
        $value = $this->json('value', []);

        return $value;
    }

    /**
     * Get a single entity from the response (for single item queries).
     *
     * @return array<string, mixed>|null
     */
    public function entity(): ?array
    {
        if ($this->hasValue()) {
            $value = $this->value();

            return $value[0] ?? null;
        }

        /** @var array<string, mixed>|null $data */
        $data = $this->decoded;

        return $data;
    }

    /**
     * Check if the response has a value array.
     */
    public function hasValue(): bool
    {
        return isset($this->decoded['value']) && is_array($this->decoded['value']);
    }

    /**
     * Get the OData count from the response.
     */
    public function count(): ?int
    {
        $count = $this->decoded['odata.count']
            ?? $this->decoded['@odata.count']
            ?? null;

        return $count !== null ? (int) $count : null;
    }

    /**
     * Get the OData next link for pagination.
     */
    public function nextLink(): ?string
    {
        /** @var string|null $nextLink */
        $nextLink = $this->decoded['odata.nextLink']
            ?? $this->decoded['@odata.nextLink']
            ?? null;

        return $nextLink;
    }

    /**
     * Check if there is a next page of results.
     */
    public function hasNextPage(): bool
    {
        return $this->nextLink() !== null;
    }

    /**
     * Get the skip token for the next page.
     */
    public function skipToken(): ?string
    {
        $nextLink = $this->nextLink();

        if ($nextLink === null) {
            return null;
        }

        $queryString = parse_url($nextLink, PHP_URL_QUERY);

        if ($queryString === false || $queryString === null) {
            return null;
        }

        parse_str($queryString, $query);

        /** @var string|null $skipToken */
        $skipToken = $query['$skiptoken'] ?? $query['$skip'] ?? null;

        return $skipToken;
    }

    /**
     * Get the OData metadata context.
     */
    public function context(): ?string
    {
        /** @var string|null $context */
        $context = $this->decoded['odata.context']
            ?? $this->decoded['@odata.context']
            ?? null;

        return $context;
    }

    /**
     * Get the error message from the response.
     */
    public function errorMessage(): ?string
    {
        /** @var string|null $message */
        $message = $this->json('error.message.value')
            ?? $this->json('error.message')
            ?? $this->json('error');

        return $message;
    }

    /**
     * Get the error code from the response.
     */
    public function errorCode(): ?string
    {
        /** @var string|null $code */
        $code = $this->json('error.code');

        return $code;
    }

    /**
     * Check if the response has an error.
     */
    public function hasError(): bool
    {
        return isset($this->decoded['error']);
    }

    /**
     * Get the underlying PSR-7 response.
     */
    public function toPsrResponse(): ResponseInterface
    {
        return $this->response;
    }

    /**
     * Get the response as an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->decoded ?? [];
    }

    /**
     * Get the response as JSON.
     *
     * @param  int  $options
     */
    public function toJson($options = 0): string
    {
        return json_encode($this->decoded, $options) ?: '{}';
    }

    /**
     * Get the JSON serializable representation.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Decode the response body as JSON.
     *
     * @return array<string, mixed>|null
     *
     * @throws JsonDecodeException
     */
    protected function decodeBody(): ?array
    {
        $body = $this->body();

        if ($body === '') {
            return null;
        }

        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw JsonDecodeException::fromLastError($body);
        }

        return $decoded;
    }

    /**
     * Dynamically access response data.
     */
    public function __get(string $key): mixed
    {
        return $this->json($key);
    }

    /**
     * Determine if the given offset exists.
     */
    public function __isset(string $key): bool
    {
        return isset($this->decoded[$key]);
    }
}
