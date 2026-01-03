<?php

declare(strict_types=1);

use SapB1\Client\SapB1Client;

describe('NextLink Parsing', function (): void {
    beforeEach(function (): void {
        config([
            'sap-b1.connections.default' => [
                'base_url' => 'https://localhost:50000',
                'company_db' => 'SBOTEST',
                'username' => 'manager',
                'password' => 'password',
            ],
        ]);
    });

    it('correctly parses nextLink with full URL', function (): void {
        // This test validates the regex parsing logic
        $nextLink = 'https://localhost:50000/b1s/v1/BusinessPartners?$skip=20&$filter=CardType%20eq%20%27C%27';

        $path = parse_url($nextLink, PHP_URL_PATH);
        $query = parse_url($nextLink, PHP_URL_QUERY);

        // Test the parsing logic from SapB1Client::nextPage
        $endpoint = preg_replace('#^/b1s/v\d+/#', '', $path);

        if ($endpoint === null || $endpoint === '' || $endpoint === $path) {
            $segments = explode('/', trim($path, '/'));
            $endpoint = end($segments) ?: '';
        }

        if ($query !== false && $query !== null) {
            $endpoint .= '?'.$query;
        }

        expect($endpoint)->toBe('BusinessPartners?$skip=20&$filter=CardType%20eq%20%27C%27');
    });

    it('handles v2 API paths', function (): void {
        $nextLink = 'https://localhost:50000/b1s/v2/Items?$skip=50';

        $path = parse_url($nextLink, PHP_URL_PATH);
        $query = parse_url($nextLink, PHP_URL_QUERY);

        $endpoint = preg_replace('#^/b1s/v\d+/#', '', $path);

        if ($endpoint === null || $endpoint === '' || $endpoint === $path) {
            $segments = explode('/', trim($path, '/'));
            $endpoint = end($segments) ?: '';
        }

        if ($query !== false && $query !== null) {
            $endpoint .= '?'.$query;
        }

        expect($endpoint)->toBe('Items?$skip=50');
    });

    it('handles nested entity paths', function (): void {
        $nextLink = 'https://localhost:50000/b1s/v1/Orders(123)/DocumentLines?$skip=10';

        $path = parse_url($nextLink, PHP_URL_PATH);
        $query = parse_url($nextLink, PHP_URL_QUERY);

        $endpoint = preg_replace('#^/b1s/v\d+/#', '', $path);

        if ($endpoint === null || $endpoint === '' || $endpoint === $path) {
            $segments = explode('/', trim($path, '/'));
            $endpoint = end($segments) ?: '';
        }

        if ($query !== false && $query !== null) {
            $endpoint .= '?'.$query;
        }

        expect($endpoint)->toBe('Orders(123)/DocumentLines?$skip=10');
    });

    it('handles relative path fallback', function (): void {
        // When regex fails, fallback to last segment
        $nextLink = '/some/unusual/path/BusinessPartners?$skip=20';

        $path = parse_url($nextLink, PHP_URL_PATH);
        $query = parse_url($nextLink, PHP_URL_QUERY);

        $endpoint = preg_replace('#^/b1s/v\d+/#', '', $path);

        if ($endpoint === null || $endpoint === '' || $endpoint === $path) {
            $segments = explode('/', trim($path, '/'));
            $endpoint = end($segments) ?: '';
        }

        if ($query !== false && $query !== null) {
            $endpoint .= '?'.$query;
        }

        expect($endpoint)->toBe('BusinessPartners?$skip=20');
    });
});
