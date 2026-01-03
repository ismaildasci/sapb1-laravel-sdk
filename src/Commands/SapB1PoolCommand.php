<?php

declare(strict_types=1);

namespace SapB1\Commands;

use Illuminate\Console\Command;
use SapB1\Contracts\SessionPoolInterface;
use SapB1\Session\Pool\PooledSession;
use SapB1\Session\Pool\SessionPool;

use function Laravel\Prompts\confirm;

class SapB1PoolCommand extends Command
{
    /**
     * @var string
     */
    public $signature = 'sap-b1:pool
                        {action : The action to perform (status, warmup, drain, cleanup, sessions)}
                        {--connection= : The connection to use}
                        {--count= : Number of sessions to warmup}
                        {--force : Force the action without confirmation}';

    /**
     * @var string
     */
    public $description = 'Manage SAP B1 session pool';

    public function handle(SessionPoolInterface $pool): int
    {
        if (! config('sap-b1.pool.enabled', false)) {
            $this->components->error('Session pool is not enabled. Set SAP_B1_POOL_ENABLED=true in your .env file.');

            return self::FAILURE;
        }

        /** @var string $action */
        $action = $this->argument('action');

        /** @var string $connection */
        $connection = $this->option('connection') ?? config('sap-b1.default', 'default');

        return match ($action) {
            'status' => $this->status($pool, $connection),
            'warmup' => $this->warmup($pool, $connection),
            'drain' => $this->drain($pool, $connection),
            'cleanup' => $this->cleanup($pool, $connection),
            'sessions' => $this->sessions($pool, $connection),
            default => $this->invalidAction($action),
        };
    }

    /**
     * Show pool status and statistics.
     */
    protected function status(SessionPoolInterface $pool, string $connection): int
    {
        $this->components->info("Session Pool Status [{$connection}]");
        $this->newLine();

        $stats = $pool->stats($connection);

        $this->components->twoColumnDetail('Total Sessions', (string) $stats['total']);
        $this->components->twoColumnDetail('Active (In Use)', (string) $stats['active']);
        $this->components->twoColumnDetail('Idle (Available)', (string) $stats['idle']);
        $this->components->twoColumnDetail('Expired', (string) $stats['expired']);
        $this->newLine();
        $this->components->twoColumnDetail('Min Size', (string) $stats['min_size']);
        $this->components->twoColumnDetail('Max Size', (string) $stats['max_size']);
        $this->components->twoColumnDetail('Algorithm', $stats['algorithm']);

        // Calculate health status
        $utilization = $stats['total'] > 0 ? ($stats['active'] / $stats['total']) * 100 : 0;
        $healthStatus = match (true) {
            $stats['total'] === 0 => '<fg=yellow>Empty</>',
            $utilization > 90 => '<fg=red>Critical</>',
            $utilization > 70 => '<fg=yellow>High Load</>',
            default => '<fg=green>Healthy</>',
        };

        $this->newLine();
        $this->components->twoColumnDetail('Pool Health', $healthStatus);
        $this->components->twoColumnDetail('Utilization', sprintf('%.1f%%', $utilization));

        return self::SUCCESS;
    }

    /**
     * Warmup the pool by pre-creating sessions.
     */
    protected function warmup(SessionPoolInterface $pool, string $connection): int
    {
        /** @var int|null $count */
        $count = $this->option('count') ? (int) $this->option('count') : null;

        $targetCount = $count ?? (int) config("sap-b1.pool.connections.{$connection}.min_size", 2);

        $this->components->info("Warming up pool [{$connection}] with {$targetCount} sessions...");

        try {
            $created = $pool->warmUp($connection, $count);

            if ($created > 0) {
                $this->components->success("Created {$created} session(s) in the pool.");
            } else {
                $this->components->info('Pool is already at desired capacity.');
            }

            $stats = $pool->stats($connection);
            $this->components->twoColumnDetail('Total Sessions', (string) $stats['total']);
            $this->components->twoColumnDetail('Idle (Available)', (string) $stats['idle']);

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->components->error('Failed to warmup pool: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Drain the pool by closing all sessions.
     */
    protected function drain(SessionPoolInterface $pool, string $connection): int
    {
        $currentSize = $pool->size($connection);

        if ($currentSize === 0) {
            $this->components->info("Pool [{$connection}] is already empty.");

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! confirm(
            "Are you sure you want to drain all {$currentSize} session(s) from [{$connection}]?",
            false
        )) {
            $this->components->info('Drain cancelled.');

            return self::SUCCESS;
        }

        $this->components->info("Draining pool [{$connection}]...");

        try {
            $drained = $pool->drain($connection);
            $this->components->success("Drained {$drained} session(s) from the pool.");

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->components->error('Failed to drain pool: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Cleanup expired sessions from the pool.
     */
    protected function cleanup(SessionPoolInterface $pool, string $connection): int
    {
        $this->components->info("Cleaning up expired sessions from [{$connection}]...");

        try {
            $removed = $pool->cleanup($connection);

            if ($removed > 0) {
                $this->components->success("Removed {$removed} expired session(s).");
            } else {
                $this->components->info('No expired sessions to clean up.');
            }

            $stats = $pool->stats($connection);
            $this->components->twoColumnDetail('Total Sessions', (string) $stats['total']);
            $this->components->twoColumnDetail('Idle (Available)', (string) $stats['idle']);

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->components->error('Failed to cleanup pool: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * List all sessions in the pool.
     */
    protected function sessions(SessionPoolInterface $pool, string $connection): int
    {
        $this->components->info("Sessions in Pool [{$connection}]");
        $this->newLine();

        // SessionPool has getStore method that returns the store
        if (! $pool instanceof SessionPool) {
            $this->components->warn('Session listing requires SessionPool implementation.');

            return self::FAILURE;
        }

        // Get all sessions via reflection or stats
        $stats = $pool->stats($connection);

        if ($stats['total'] === 0) {
            $this->components->info('Pool is empty. No sessions to display.');

            return self::SUCCESS;
        }

        // Display summary table
        $this->table(
            ['Status', 'Count'],
            [
                [PooledSession::STATUS_IDLE, $stats['idle']],
                [PooledSession::STATUS_ACTIVE, $stats['active']],
                [PooledSession::STATUS_EXPIRED, $stats['expired']],
            ]
        );

        $this->newLine();
        $this->components->twoColumnDetail('Total', (string) $stats['total']);

        return self::SUCCESS;
    }

    /**
     * Handle invalid action.
     */
    protected function invalidAction(string $action): int
    {
        $this->components->error("Invalid action: {$action}");
        $this->newLine();
        $this->components->info('Available actions:');
        $this->components->bulletList([
            'status   - Show pool statistics and health',
            'warmup   - Pre-create sessions (--count=N to specify)',
            'drain    - Close and remove all sessions',
            'cleanup  - Remove expired sessions',
            'sessions - List session summary by status',
        ]);

        return self::FAILURE;
    }
}
