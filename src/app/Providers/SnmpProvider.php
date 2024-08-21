<?php

namespace D4m111\SnmpManager\App\Providers;

use D4m111\SnmpManager\App\Console\Commands;

use Illuminate\Support\ServiceProvider;

class SnmpProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        
        // $this->loadRoutesFrom(__DIR__.'/../../routes/api.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                Commands\SnmpIterateCommand::class,
                Commands\SnmpGetCommand::class,
                Commands\SnmpWalkCommand::class,
                Commands\SnmpSetCommand::class,
            ]);
        }

    }
}