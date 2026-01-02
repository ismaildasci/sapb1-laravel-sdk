<?php

declare(strict_types=1);

namespace SapB1\Commands;

use Illuminate\Console\Command;
use SapB1\Session\SessionManager;

use function Laravel\Prompts\confirm;

class SapB1SessionCommand extends Command
{
    /**
     * @var string
     */
    public $signature = 'sap-b1:session
                        {action : The action to perform (login, logout, refresh, clear, clear-all)}
                        {--connection= : The connection to use}
                        {--force : Force the action without confirmation}';

    /**
     * @var string
     */
    public $description = 'Manage SAP B1 sessions';

    public function handle(SessionManager $sessionManager): int
    {
        /** @var string $action */
        $action = $this->argument('action');

        /** @var string $connection */
        $connection = $this->option('connection') ?? config('sap-b1.default', 'default');

        return match ($action) {
            'login' => $this->login($sessionManager, $connection),
            'logout' => $this->logout($sessionManager, $connection),
            'refresh' => $this->refresh($sessionManager, $connection),
            'clear' => $this->clear($sessionManager, $connection),
            'clear-all' => $this->clearAll($sessionManager),
            default => $this->invalidAction($action),
        };
    }

    /**
     * Login to SAP B1.
     */
    protected function login(SessionManager $sessionManager, string $connection): int
    {
        $this->components->info("Logging in to SAP B1 [{$connection}]...");

        try {
            $session = $sessionManager->getSession($connection);

            $this->components->success('Successfully logged in!');
            $this->components->twoColumnDetail('Session ID', $session->sessionId);
            $this->components->twoColumnDetail('Company', $session->companyDb);
            $this->components->twoColumnDetail('Expires At', $session->expiresAt->format('Y-m-d H:i:s'));

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->components->error('Failed to login: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Logout from SAP B1.
     */
    protected function logout(SessionManager $sessionManager, string $connection): int
    {
        if (! $sessionManager->hasValidSession($connection)) {
            $this->components->warn("No active session for [{$connection}]");

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! confirm("Are you sure you want to logout from [{$connection}]?")) {
            $this->components->info('Logout cancelled.');

            return self::SUCCESS;
        }

        $this->components->info("Logging out from SAP B1 [{$connection}]...");

        try {
            $sessionManager->logout($connection);
            $this->components->success('Successfully logged out!');

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->components->error('Failed to logout: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Refresh the session.
     */
    protected function refresh(SessionManager $sessionManager, string $connection): int
    {
        $this->components->info("Refreshing session for [{$connection}]...");

        try {
            $session = $sessionManager->refreshSession($connection);

            $this->components->success('Session refreshed successfully!');
            $this->components->twoColumnDetail('Session ID', $session->sessionId);
            $this->components->twoColumnDetail('Expires At', $session->expiresAt->format('Y-m-d H:i:s'));

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->components->error('Failed to refresh session: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Clear session without logging out from SAP B1.
     */
    protected function clear(SessionManager $sessionManager, string $connection): int
    {
        if (! $this->option('force') && ! confirm("Are you sure you want to clear the session for [{$connection}]?")) {
            $this->components->info('Clear cancelled.');

            return self::SUCCESS;
        }

        $this->components->info("Clearing session for [{$connection}]...");

        $sessionManager->clearSession($connection);
        $this->components->success('Session cleared!');

        return self::SUCCESS;
    }

    /**
     * Clear all sessions.
     */
    protected function clearAll(SessionManager $sessionManager): int
    {
        if (! $this->option('force') && ! confirm('Are you sure you want to clear ALL SAP B1 sessions?', false)) {
            $this->components->info('Clear cancelled.');

            return self::SUCCESS;
        }

        $this->components->info('Clearing all sessions...');

        $sessionManager->clearAllSessions();
        $this->components->success('All sessions cleared!');

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
            'login    - Login to SAP B1 and create a session',
            'logout   - Logout from SAP B1 and destroy the session',
            'refresh  - Refresh the current session',
            'clear    - Clear the local session (without SAP logout)',
            'clear-all - Clear all stored sessions',
        ]);

        return self::FAILURE;
    }
}
