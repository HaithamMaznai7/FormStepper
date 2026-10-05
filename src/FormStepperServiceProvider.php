<?php

declare(strict_types=1);

namespace FormStepper\FormStepper;

use FormStepper\FormStepper\Console\Commands\FormStepperCommand;
use FormStepper\FormStepper\Forms\FormBuilderRegistry;
use FormStepper\FormStepper\Support\SchemaNormalizer;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class FormStepperServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/form-stepper.php', 'form-stepper');

        $this->app->singleton(FormStepper::class);
        $this->app->singleton(SchemaNormalizer::class);
        $this->app->singleton(FormBuilderRegistry::class, function (Application $app): FormBuilderRegistry {
            $registry = new FormBuilderRegistry;

            foreach (config('form-stepper.builders', []) as $type => $builderClass) {
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
        $this->loadRoutesFrom(__DIR__.'/../routes/form-stepper.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'form-stepper');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'form-stepper');

        if (! $this->app->runningInConsole()) {
            return;
        }
        $this->publishes([
            __DIR__.'/../config/form-stepper.php' => config_path('form-stepper.php'),
        ], ['form-stepper', 'form-stepper-config']);
        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/form-stepper'),
        ], ['form-stepper', 'form-stepper-views']);
        $this->publishes([
            __DIR__.'/../lang' => $this->app->langPath('vendor/form-stepper'),
        ], ['form-stepper', 'form-stepper-lang']);
        $this->publishes([
            __DIR__.'/../public' => public_path('vendor/form-stepper'),
        ], ['form-stepper', 'form-stepper-assets']);
        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], ['form-stepper', 'form-stepper-migrations']);
        $this->commands([
            FormStepperCommand::class,
        ]);
    }
}
