<?php

declare(strict_types=1);

namespace SapB1\Facades;

use Illuminate\Support\Facades\Facade;
use SapB1\Client\ODataBuilder;
use SapB1\Client\Response;
use SapB1\Client\SapB1Client;

/**
 * @method static SapB1Client connection(string $connection)
 * @method static ODataBuilder query()
 * @method static SapB1Client withOData(ODataBuilder $odata)
 * @method static Response get(string $endpoint)
 * @method static Response find(string $endpoint, mixed $key)
 * @method static Response create(string $endpoint, array<string, mixed> $data)
 * @method static Response update(string $endpoint, mixed $key, array<string, mixed> $data)
 * @method static Response delete(string $endpoint, mixed $key)
 * @method static Response action(string $endpoint, mixed $key, string $action, array<string, mixed> $params = [])
 * @method static Response|null nextPage(Response $response)
 * @method static \Generator<int, Response> paginate(string $endpoint)
 * @method static int count(string $endpoint)
 * @method static bool exists(string $endpoint, mixed $key)
 * @method static Response post(string $endpoint, array<string, mixed> $data = [])
 * @method static Response put(string $endpoint, array<string, mixed> $data = [])
 * @method static Response patch(string $endpoint, array<string, mixed> $data = [])
 * @method static Response rawDelete(string $endpoint)
 * @method static void logout()
 * @method static void refreshSession()
 * @method static bool hasValidSession()
 * @method static string getConnection()
 *
 * @see \SapB1\Client\SapB1Client
 */
class SapB1 extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SapB1Client::class;
    }
}
