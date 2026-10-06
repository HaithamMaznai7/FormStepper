<?php

declare(strict_types=1);

use HaithamMaznai\FormStepper\FormStepperServiceProvider;
use HaithamMaznai\FormStepper\Http\Controllers\FormController;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

it('publishes routes and an application controller under separate tags', function () {
    $routes = ServiceProvider::pathsToPublish(FormStepperServiceProvider::class, 'form-stepper-routes');
    $controllers = ServiceProvider::pathsToPublish(FormStepperServiceProvider::class, 'form-stepper-controller');

    expect(array_values($routes))->toBe([base_path('routes/form-stepper.php')])
        ->and(array_values($controllers))->toBe([app_path('Http/Controllers/FormStepperController.php')]);
    $controller = file_get_contents(array_key_first($controllers));
    expect($controller)->toContain('namespace App\Http\Controllers;', 'extends FormController');
});

it('uses the configured controller for all package routes', function () {
    config()->set('form-stepper.routes.controller', 'App\Http\Controllers\FormStepperController');
    Route::setRoutes(new RouteCollection);
    require __DIR__.'/../../routes/form-stepper.php';

    foreach (Route::getRoutes() as $route) {
        expect($route->getActionName())->toStartWith('App\Http\Controllers\FormStepperController@');
    }
});

it('loads a published route file instead of the bundled routes', function () {
    $basePath = $this->app->basePath();
    $temporaryPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'form-stepper-routes-'.bin2hex(random_bytes(8));
    mkdir($temporaryPath);
    mkdir($temporaryPath.DIRECTORY_SEPARATOR.'routes');
    $this->app->setBasePath($temporaryPath);
    $path = base_path('routes/form-stepper.php');
    expect(file_exists($path))->toBeFalse();
    file_put_contents($path, '<?php \Illuminate\Support\Facades\Route::get("/published-forms", fn () => "published")->name("published.forms");');

    try {
        Route::setRoutes(new RouteCollection);
        (new FormStepperServiceProvider($this->app))->boot();
        Route::getRoutes()->refreshNameLookups();
        expect(Route::has('published.forms'))->toBeTrue()
            ->and(Route::has('form-stepper.forms.store'))->toBeFalse();
    } finally {
        unlink($path);
        $this->app->setBasePath($basePath);
        rmdir($temporaryPath.DIRECTORY_SEPARATOR.'routes');
        rmdir($temporaryPath);
    }
});

it('does not reload a published route file when routes are cached', function () {
    // A real cache file works across Laravel 11+, unlike the newer `routes.cached` binding.
    $cacheFile = 'form-stepper-routes-cache-'.bin2hex(random_bytes(8)).'.php';
    $_SERVER['APP_ROUTES_CACHE'] = $cacheFile;
    file_put_contents(base_path($cacheFile), '<?php');
    $this->app->forgetInstance('routes.cached');

    try {
        expect($this->app->routesAreCached())->toBeTrue();
        Route::setRoutes(new RouteCollection);
        (new FormStepperServiceProvider($this->app))->boot();
        expect(Route::getRoutes()->count())->toBe(0);
    } finally {
        unset($_SERVER['APP_ROUTES_CACHE']);
        unlink(base_path($cacheFile));
        $this->app->forgetInstance('routes.cached');
    }
});

it('does not load bundled or published form routes when disabled', function () {
    config()->set('form-stepper.routes.enabled', false);
    Route::setRoutes(new RouteCollection);
    (new FormStepperServiceProvider($this->app))->boot();
    expect(Route::has('form-stepper.forms.store'))->toBeFalse();
});

it('keeps the package controller as the default', function () {
    expect(Route::getRoutes()->getByName('form-stepper.forms.store')->getActionName())
        ->toBe(FormController::class.'@store');
});
