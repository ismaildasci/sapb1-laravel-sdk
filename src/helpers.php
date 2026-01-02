<?php

declare(strict_types=1);

use SapB1\Client\SapB1Client;

if (! function_exists('sap_b1')) {
    /**
     * Get the SAP B1 client instance.
     *
     * @param  string|null  $connection  The connection name to use
     */
    function sap_b1(?string $connection = null): SapB1Client
    {
        /** @var SapB1Client $client */
        $client = app(SapB1Client::class);

        if ($connection !== null) {
            return $client->connection($connection);
        }

        return $client;
    }
}
