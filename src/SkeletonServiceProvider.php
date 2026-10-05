<?php

declare(strict_types=1);

namespace VendorName\Skeleton;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
/* @chisel-commands */
use VendorName\Skeleton\Console\Commands\SkeletonCommand;
/* @end-chisel-commands */
use VendorName\Skeleton\Forms\FormBuilderRegistry;
use VendorName\Skeleton\Support\SchemaNormalizer;

class SkeletonServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        /* @chisel-config */
        $this->mergeConfigFrom(__DIR__.'/../config/skeleton.php', 'skeleton');
        /* @end-chisel-config */

        $this->app->singleton(Skeleton::class);
        $this->app->singleton(SchemaNormalizer::class);
        $this->app->singleton(FormBuilderRegistry::class, function (Application $app): FormBuilderRegistry {
            $registry = new FormBuilderRegistry;

            foreach (config('skeleton.builders', []) as $type => $builderClass) {
                $registry->register((string) $type, $app->make($builderClass));
            }

            return $registry;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /* @chisel-routes */
        $this->loadRoutesFrom(__DIR__.'/../routes/skeleton.php');
        /* @end-chisel-routes */

        /* @chisel-views */
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'skeleton');
        /* @end-chisel-views */

        /* @chisel-translations */
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'skeleton');
        /* @end-chisel-translations */

        /* @chisel-any-features */
        if (! $this->app->runningInConsole()) {
            return;
        }
        /* @end-chisel-any-features */

        /* @chisel-config */
        $this->publishes([
            __DIR__.'/../config/skeleton.php' => config_path('skeleton.php'),
        ], ['skeleton', 'skeleton-config']);
        /* @end-chisel-config */

        /* @chisel-views */
        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/skeleton'),
        ], ['skeleton', 'skeleton-views']);
        /* @end-chisel-views */

        /* @chisel-translations */
        $this->publishes([
            __DIR__.'/../lang' => $this->app->langPath('vendor/skeleton'),
        ], ['skeleton', 'skeleton-lang']);
        /* @end-chisel-translations */

        /* @chisel-assets */
        $this->publishes([
            __DIR__.'/../public' => public_path('vendor/skeleton'),
        ], ['skeleton', 'skeleton-assets']);
        /* @end-chisel-assets */

        /* @chisel-migrations */
        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], ['skeleton', 'skeleton-migrations']);
        /* @end-chisel-migrations */

        /* @chisel-commands */
        $this->commands([
            SkeletonCommand::class,
        ]);
        /* @end-chisel-commands */
    }
}
