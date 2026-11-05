<?php

namespace Javaabu\Meta;

use Illuminate\Support\ServiceProvider;
use Javaabu\Stencil\Fields\Field;

class MetaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/meta.php', 'meta');

        $this->app->singleton(MetaFields::class, function () {
            $registry = new MetaFields;

            foreach ((array) config('meta.types', []) as $type) {
                $registry->type($type);
            }

            return $registry;
        });
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__ . '/../lang', 'meta');

        $this->registerFieldMacros();

        if (! $this->app->runningInConsole()) {
            return;
        }

        if (config('meta.run_migrations', true)) {
            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        }

        $this->registerPublishing();
    }

    /**
     * ->metaType('integer') on a stencil field stores its values as that type.
     */
    protected function registerFieldMacros(): void
    {
        if (Field::hasMacro('metaType')) {
            return;
        }

        Field::macro('metaType', function (string $type): Field {
            /** @var Field $this */
            app(MetaFields::class)->setFieldType($this, $type);

            return $this;
        });
    }

    protected function registerPublishing(): void
    {
        $this->publishes([
            __DIR__ . '/../config/meta.php' => config_path('meta.php'),
        ], 'meta-config');

        $this->publishesMigrations([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'meta-migrations');

        $this->publishes([
            __DIR__ . '/../lang' => lang_path('vendor/meta'),
        ], 'meta-translations');
    }
}
