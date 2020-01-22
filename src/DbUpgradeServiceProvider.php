<?php

namespace Exolnet\DbUpgrade;

use Illuminate\Support\ServiceProvider;

class DbUpgradeServiceProvider extends ServiceProvider
{
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
