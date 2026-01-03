<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use SapB1\Client\SapB1Client;
use SapB1\Session\Drivers\FileSessionDriver;
use SapB1\Session\SessionManager;

describe('OData Version Support', function (): void {
    beforeEach(function (): void {
        config([
            'sap-b1.connections.default' => [
                'base_url' => 'https://localhost:50000',
                'company_db' => 'SBOTEST',
                'username' => 'manager',
                'password' => 'password',
                'odata_version' => 'v1', // Default v3
            ],
            'sap-b1.connections.v4_connection' => [
                'base_url' => 'https://localhost:50000',
                'company_db' => 'SBOTEST',
                'username' => 'manager',
                'password' => 'password',
                'odata_version' => 'v2', // OData v4
            ],
        ]);
    });

    it('defaults to OData v1 (v3)', function (): void {
        $driver = new FileSessionDriver(new Filesystem);
        $manager = new SessionManager($driver);
        $client = new SapB1Client($manager);

        expect($client->getODataVersion())->toBe('v1');
    });

    it('can switch to OData v4 with useODataV4', function (): void {
        $driver = new FileSessionDriver(new Filesystem);
        $manager = new SessionManager($driver);
        $client = new SapB1Client($manager);

        $v4Client = $client->useODataV4();

        expect($v4Client->getODataVersion())->toBe('v2');
        // Original client unchanged
        expect($client->getODataVersion())->toBe('v1');
    });

    it('can switch to OData v3 with useODataV3', function (): void {
        $driver = new FileSessionDriver(new Filesystem);
        $manager = new SessionManager($driver);
        $client = new SapB1Client($manager);

        $v4Client = $client->useODataV4();
        $v3Client = $v4Client->useODataV3();

        expect($v3Client->getODataVersion())->toBe('v1');
    });

    it('can set custom OData version', function (): void {
        $driver = new FileSessionDriver(new Filesystem);
        $manager = new SessionManager($driver);
        $client = new SapB1Client($manager);

        $customClient = $client->withODataVersion('v2');

        expect($customClient->getODataVersion())->toBe('v2');
    });

    it('uses config-based OData version for connections', function (): void {
        $driver = new FileSessionDriver(new Filesystem);
        $manager = new SessionManager($driver);
        $client = new SapB1Client($manager);

        $v4Client = $client->connection('v4_connection');

        expect($v4Client->getODataVersion())->toBe('v2');
    });
});
