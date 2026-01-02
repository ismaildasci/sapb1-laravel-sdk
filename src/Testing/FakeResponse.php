<?php

declare(strict_types=1);

namespace SapB1\Testing;

use GuzzleHttp\Psr7\Response as Psr7Response;
use SapB1\Client\Response;

class FakeResponse
{
    /**
     * Create a successful response with data.
     *
     * @param  array<string, mixed>  $data
     */
    public static function make(array $data = [], int $status = 200): Response
    {
        $body = json_encode($data) ?: '{}';

        return new Response(
            new Psr7Response($status, ['Content-Type' => 'application/json'], $body)
        );
    }

    /**
     * Create a response with OData value array.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    public static function collection(array $items, ?int $count = null, ?string $nextLink = null): Response
    {
        $data = ['value' => $items];

        if ($count !== null) {
            $data['odata.count'] = $count;
        }

        if ($nextLink !== null) {
            $data['odata.nextLink'] = $nextLink;
        }

        return self::make($data);
    }

    /**
     * Create a response for a single entity.
     *
     * @param  array<string, mixed>  $entity
     */
    public static function entity(array $entity): Response
    {
        return self::make($entity);
    }

    /**
     * Create an empty successful response.
     */
    public static function empty(int $status = 204): Response
    {
        return new Response(
            new Psr7Response($status, ['Content-Type' => 'application/json'], '')
        );
    }

    /**
     * Create an error response.
     */
    public static function error(string $message, ?string $code = null, int $status = 400): Response
    {
        $error = ['message' => ['value' => $message]];

        if ($code !== null) {
            $error['code'] = $code;
        }

        return self::make(['error' => $error], $status);
    }

    /**
     * Create a not found response.
     */
    public static function notFound(string $message = 'Resource not found'): Response
    {
        return self::error($message, '-1', 404);
    }

    /**
     * Create an unauthorized response.
     */
    public static function unauthorized(string $message = 'Session expired or invalid'): Response
    {
        return self::error($message, '301', 401);
    }

    /**
     * Create a validation error response.
     */
    public static function validationError(string $message): Response
    {
        return self::error($message, '-2028', 400);
    }

    /**
     * Create a server error response.
     */
    public static function serverError(string $message = 'Internal server error'): Response
    {
        return self::error($message, '-1', 500);
    }

    /**
     * Create a response from a JSON file.
     */
    public static function fromFile(string $path, int $status = 200): Response
    {
        $content = file_get_contents($path);

        if ($content === false) {
            $content = '{}';
        }

        return new Response(
            new Psr7Response($status, ['Content-Type' => 'application/json'], $content)
        );
    }
}
