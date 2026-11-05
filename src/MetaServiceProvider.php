<?php

namespace Javaabu\Meta;

use Illuminate\Support\ServiceProvider;

class MetaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/meta.php', 'meta');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->registerPublishing();
        }
    }

    protected function registerPublishing(): void
    {
        $this->publishes([
            __DIR__ . '/../config/meta.php' => config_path('meta.php'),
        ], 'meta-config');
    }
}
