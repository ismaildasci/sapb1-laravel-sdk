<?php

declare(strict_types=1);

namespace SapB1\Commands;

use Illuminate\Console\Command;
use SapB1\Client\SapB1Client;
use SapB1\Session\SessionManager;

class SapB1StatusCommand extends Command
{
    /**
     * @var string
     */
    public $signature = 'sap-b1:status
                        {--connection= : The connection to check}
                        {--test : Test the connection by making an API call}';

    /**
     * @var string
     */
    public $description = 'Show SAP B1 connection status and configuration';

    public function handle(SessionManager $sessionManager, SapB1Client $client): int
    {
        $connection = $this->option('connection') ?? config('sap-b1.default', 'default');

        $this->components->info("SAP B1 Connection Status: {$connection}");

        $this->showConfiguration($connection);
        $this->showSessionStatus($sessionManager, $connection);

        if ($this->option('test')) {
            $this->testConnection($client, $connection);
        }

        return self::SUCCESS;
    }

    /**
     * Show configuration for the connection.
     */
    protected function showConfiguration(string $connection): void
    {
        $this->newLine();
        $this->components->twoColumnDetail('<fg=gray>Configuration</>');

        /** @var array<string, mixed>|null $config */
        $config = config("sap-b1.connections.{$connection}");

        if ($config === null) {
            $this->components->error("Connection [{$connection}] not configured");

            return;
        }

        $baseUrl = $config['base_url'] ?? 'Not set';
        $companyDb = $config['company_db'] ?? 'Not set';
        $username = $config['username'] ?? 'Not set';
        $language = $config['language'] ?? 23;

        $this->components->twoColumnDetail('Base URL', (string) $baseUrl);
        $this->components->twoColumnDetail('Company DB', (string) $companyDb);
        $this->components->twoColumnDetail('Username', (string) $username);
        $this->components->twoColumnDetail('Language', (string) $language);

        $this->newLine();
        $this->components->twoColumnDetail('<fg=gray>Session Configuration</>');

        $driver = config('sap-b1.session.driver', 'file');
        $ttl = config('sap-b1.session.ttl', 1680);
        $refreshThreshold = config('sap-b1.session.refresh_threshold', 300);

        $this->components->twoColumnDetail('Driver', (string) $driver);
        $this->components->twoColumnDetail('TTL', $ttl.' seconds');
        $this->components->twoColumnDetail('Refresh Threshold', $refreshThreshold.' seconds');

        $this->newLine();
        $this->components->twoColumnDetail('<fg=gray>HTTP Configuration</>');

        $timeout = config('sap-b1.http.timeout', 30);
        $verify = config('sap-b1.http.verify', true) ? 'Yes' : 'No';
        $retryTimes = config('sap-b1.http.retry.times', 3);

        $this->components->twoColumnDetail('Timeout', $timeout.' seconds');
        $this->components->twoColumnDetail('SSL Verify', $verify);
        $this->components->twoColumnDetail('Retry Times', (string) $retryTimes);
    }

    /**
     * Show session status.
     */
    protected function showSessionStatus(SessionManager $sessionManager, string $connection): void
    {
        $this->newLine();
        $this->components->twoColumnDetail('<fg=gray>Session Status</>');

        if ($sessionManager->hasValidSession($connection)) {
            $this->components->twoColumnDetail('Status', '<fg=green>Active</>');

            $session = $sessionManager->getSession($connection);
            $this->components->twoColumnDetail('Session ID', $session->sessionId);
            $this->components->twoColumnDetail('Company', $session->companyDb);
            $this->components->twoColumnDetail('Expires At', $session->expiresAt->format('Y-m-d H:i:s'));
            $this->components->twoColumnDetail('Created At', $session->createdAt->format('Y-m-d H:i:s'));

            $remainingMinutes = (int) $session->expiresAt->diffInMinutes(now());
            $this->components->twoColumnDetail('Remaining', $remainingMinutes.' minutes');
        } else {
            $this->components->twoColumnDetail('Status', '<fg=yellow>No active session</>');
        }
    }

    /**
     * Test the connection by making an API call.
     */
    protected function testConnection(SapB1Client $client, string $connection): void
    {
        $this->newLine();
        $this->components->twoColumnDetail('<fg=gray>Connection Test</>');

        try {
            $connectionClient = $connection !== config('sap-b1.default', 'default')
                ? $client->connection($connection)
                : $client;

            $startTime = microtime(true);
            $response = $connectionClient->get('CompanyService_GetCompanyInfo');
            $duration = round((microtime(true) - $startTime) * 1000);

            if ($response->successful()) {
                $this->components->twoColumnDetail('Result', '<fg=green>Connected successfully</>');
                $this->components->twoColumnDetail('Response Time', $duration.'ms');

                $companyName = $response->json('CompanyName');
                if ($companyName !== null) {
                    $this->components->twoColumnDetail('Company Name', (string) $companyName);
                }
            } else {
                $this->components->twoColumnDetail('Result', '<fg=red>Connection failed</>');
                $this->components->twoColumnDetail('Error', $response->errorMessage() ?? 'Unknown error');
            }
        } catch (\Exception $e) {
            $this->components->twoColumnDetail('Result', '<fg=red>Connection failed</>');
            $this->components->twoColumnDetail('Error', $e->getMessage());
        }
    }
}
