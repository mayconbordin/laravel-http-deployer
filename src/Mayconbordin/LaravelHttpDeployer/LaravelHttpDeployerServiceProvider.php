<?php namespace Mayconbordin\LaravelHttpDeployer;

use Illuminate\Support\ServiceProvider;
use Mayconbordin\LaravelHttpDeployer\Commands\DeployCommand;

class LaravelHttpDeployerServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->commands([
            DeployCommand::class
        ]);
    }
}