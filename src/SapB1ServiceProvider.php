<?php

declare(strict_types=1);

namespace SapB1;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\DatabaseManager;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Redis\RedisManager;
use SapB1\Client\SapB1Client;
use SapB1\Commands\SapB1SessionCommand;
use SapB1\Commands\SapB1StatusCommand;
use SapB1\Contracts\SessionStoreInterface;
use SapB1\Session\Drivers\DatabaseSessionDriver;
use SapB1\Session\Drivers\FileSessionDriver;
use SapB1\Session\Drivers\RedisSessionDriver;
use SapB1\Session\SessionManager;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class SapB1ServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('sap-b1')
            ->hasConfigFile()
            ->hasMigration('create_sapb1_table')
            ->hasCommands([
                SapB1StatusCommand::class,
                SapB1SessionCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        $this->registerSessionStore();
        $this->registerSessionManager();
        $this->registerClient();
    }

    public function packageBooted(): void
    {
        $this->bootAboutCommand();
    }

    /**
     * Register the session store based on configuration.
     */
    protected function registerSessionStore(): void
    {
        $this->app->singleton(SessionStoreInterface::class, function (Application $app): SessionStoreInterface {
            /** @var string $driver */
            $driver = config('sap-b1.session.driver', 'file');

            return match ($driver) {
                'redis' => new RedisSessionDriver(
                    $app->make(RedisManager::class),
                    (string) config('sap-b1.session.redis_connection', 'default')
                ),
                'database' => new DatabaseSessionDriver(
                    $app->make(DatabaseManager::class),
                    config('sap-b1.session.database_connection')
                ),
                default => new FileSessionDriver(
                    $app->make(Filesystem::class)
                ),
            };
        });
    }

    /**
     * Register the session manager.
     */
    protected function registerSessionManager(): void
    {
        $this->app->singleton(SessionManager::class, function (Application $app): SessionManager {
            return new SessionManager(
                $app->make(SessionStoreInterface::class)
            );
        });
    }

    /**
     * Register the SAP B1 client.
     */
    protected function registerClient(): void
    {
        $this->app->singleton(SapB1Client::class, function (Application $app): SapB1Client {
            return new SapB1Client(
                $app->make(SessionManager::class)
            );
        });

        // Alias for convenience
        $this->app->alias(SapB1Client::class, 'sap-b1');
    }

    /**
     * Boot the about command integration.
     */
    protected function bootAboutCommand(): void
    {
        if (class_exists(\Illuminate\Foundation\Console\AboutCommand::class)) {
            \Illuminate\Foundation\Console\AboutCommand::add('SAP B1 SDK', fn (): array => [
                'Version' => '1.0.0',
                'Session Driver' => (string) config('sap-b1.session.driver', 'file'),
                'Default Connection' => (string) config('sap-b1.default', 'default'),
            ]);
        }
    }
}
