<?php

declare(strict_types=1);

namespace SapB1;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class SapB1ServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('sap-b1')
            ->hasConfigFile();
    }

    public function packageRegistered(): void
    {
        // Singleton bindings eklenecek
    }

    public function packageBooted(): void
    {
        // About command entegrasyonu
        if (class_exists(\Illuminate\Foundation\Console\AboutCommand::class)) {
            \Illuminate\Foundation\Console\AboutCommand::add('SAP B1 SDK', fn () => [
                'Version' => '1.0.0',
                'Session Driver' => config('sap-b1.session.driver'),
            ]);
        }
    }
}
