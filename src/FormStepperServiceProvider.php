<?php

declare(strict_types=1);

namespace HaithamMaznai\FormStepper;

use HaithamMaznai\FormStepper\Console\Commands\FormStepperCommand;
use HaithamMaznai\FormStepper\Forms\FormBuilderRegistry;
use HaithamMaznai\FormStepper\Support\SchemaNormalizer;
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
        if (config('form-stepper.routes.enabled', true)) {
            $publishedRoutes = base_path('routes/form-stepper.php');
            $this->loadRoutesFrom(is_file($publishedRoutes) ? $publishedRoutes : __DIR__.'/../routes/form-stepper.php');
        }

        if (config('form-stepper.admin.enabled', false)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/form-stepper-admin.php');
        }
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'form-stepper');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'form-stepper');

        if (! $this->app->runningInConsole()) {
            return;
        }
        $this->publishes([
            __DIR__.'/../config/form-stepper.php' => config_path('form-stepper.php'),
        ], ['form-stepper', 'form-stepper-config']);
        $this->publishes([
            __DIR__.'/../routes/form-stepper.php' => base_path('routes/form-stepper.php'),
        ], ['form-stepper', 'form-stepper-routes']);
        $this->publishes([
            __DIR__.'/../resources/stubs/FormStepperController.php.stub' => app_path('Http/Controllers/FormStepperController.php'),
        ], ['form-stepper', 'form-stepper-controller']);
        $this->publishes(collect(['InputTypeController', 'InputController', 'StepTemplateController', 'FormController'])
            ->mapWithKeys(fn (string $name): array => [
                __DIR__."/../resources/stubs/admin/{$name}.php.stub" => app_path("Http/Controllers/FormStepper/Admin/{$name}.php"),
            ])->all(), 'form-stepper-admin-controllers');
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
