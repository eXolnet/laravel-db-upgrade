<?php

namespace Exolnet\DbUpgrade;

use Illuminate\Support\ServiceProvider;

class DbUpgradeServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__ . '/../config/db-upgrade.php' => config_path('db-upgrade.php'),
        ], 'config');
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            Console\DbUpgradeCommand::class,
        ]);
    }
}
