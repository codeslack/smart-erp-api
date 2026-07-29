<?php

namespace App\Core\Modules;

use Illuminate\Support\ServiceProvider;


class ModuleServiceProvider
    extends ServiceProvider
{
    public function register(): void
    {
        foreach (
            ModuleRegistry::serviceProviders()
            as $provider
        ) {

            $this->app->register(
                $provider
            );
        }
    }
}