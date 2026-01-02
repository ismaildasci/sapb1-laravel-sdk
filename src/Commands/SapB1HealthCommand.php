<?php

declare(strict_types=1);

namespace SapB1\Commands;

use Illuminate\Console\Command;
use SapB1\Health\SapB1HealthCheck;

class SapB1HealthCommand extends Command
{
    /**
     * @var string
     */
    public $signature = 'sap-b1:health
                        {--connection= : Check a specific connection}
                        {--all : Check all configured connections}
                        {--json : Output as JSON}';

    /**
     * @var string
     */
    public $description = 'Check SAP B1 connection health';

    public function handle(SapB1HealthCheck $healthCheck): int
    {
        if ($this->option('all')) {
            return $this->checkAll($healthCheck);
        }

        return $this->checkSingle($healthCheck);
    }

    /**
     * Check a single connection.
     */
    protected function checkSingle(SapB1HealthCheck $healthCheck): int
    {
        /** @var string|null $connection */
        $connection = $this->option('connection');

        $this->components->info('Checking SAP B1 connection health...');

        $result = $healthCheck->check($connection);

        if ($this->option('json')) {
            $this->line(json_encode($result->toArray(), JSON_PRETTY_PRINT) ?: '{}');

            return $result->isHealthy() ? self::SUCCESS : self::FAILURE;
        }

        $this->newLine();

        if ($result->isHealthy()) {
            $this->components->success($result->message);
        } else {
            $this->components->error($result->message);
        }

        $this->components->twoColumnDetail('Status', $result->isHealthy()
            ? '<fg=green>Healthy</>'
            : '<fg=red>Unhealthy</>');

        if ($result->connection !== null) {
            $this->components->twoColumnDetail('Connection', $result->connection);
        }

        if ($result->companyDb !== null) {
            $this->components->twoColumnDetail('Company DB', $result->companyDb);
        }

        if ($result->responseTime !== null) {
            $this->components->twoColumnDetail('Response Time', round($result->responseTime, 2).'ms');
        }

        if ($result->sessionId !== null) {
            $this->components->twoColumnDetail('Session ID', $result->sessionId);
        }

        return $result->isHealthy() ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Check all connections.
     */
    protected function checkAll(SapB1HealthCheck $healthCheck): int
    {
        $this->components->info('Checking all SAP B1 connections...');

        $results = $healthCheck->checkAll();

        if ($this->option('json')) {
            $output = [];
            foreach ($results as $connection => $result) {
                $output[$connection] = $result->toArray();
            }
            $this->line(json_encode($output, JSON_PRETTY_PRINT) ?: '{}');

            return $healthCheck->isHealthy() ? self::SUCCESS : self::FAILURE;
        }

        $allHealthy = true;

        foreach ($results as $connection => $result) {
            $this->newLine();
            $this->components->twoColumnDetail(
                "<fg=cyan>{$connection}</>",
                $result->isHealthy()
                    ? '<fg=green>Healthy</>'
                    : '<fg=red>Unhealthy</>'
            );

            if ($result->isUnhealthy()) {
                $allHealthy = false;
                $this->components->twoColumnDetail('  Error', $result->message);
            } else {
                if ($result->companyDb !== null) {
                    $this->components->twoColumnDetail('  Company', $result->companyDb);
                }
                if ($result->responseTime !== null) {
                    $this->components->twoColumnDetail('  Response', round($result->responseTime, 2).'ms');
                }
            }
        }

        $this->newLine();

        if ($allHealthy) {
            $this->components->success('All connections are healthy!');
        } else {
            $this->components->error('Some connections are unhealthy.');
        }

        return $allHealthy ? self::SUCCESS : self::FAILURE;
    }
}
