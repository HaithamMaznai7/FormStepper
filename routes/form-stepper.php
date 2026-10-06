<?php

declare(strict_types=1);

use HaithamMaznai\FormStepper\Http\Controllers\FormController;
use Illuminate\Support\Facades\Route;

$options = config('form-stepper.routes', []);

if (! ($options['enabled'] ?? true)) {
    return;
}

$controller = $options['controller'] ?? FormController::class;

Route::prefix($options['prefix'] ?? 'api/forms')
    ->middleware($options['middleware'] ?? ['api'])
    ->name($options['name'] ?? 'form-stepper.forms.')
    ->group(function () use ($controller): void {
        Route::get('/', [$controller, 'index'])->name('index');
        Route::post('/', [$controller, 'store'])->name('store');
        Route::get('/{uuid}', [$controller, 'show'])->name('show');
        Route::patch('/{uuid}/options', [$controller, 'updateOptions'])->name('options.update');
        Route::put('/{uuid}/steps/{step}', [$controller, 'saveStep'])->name('steps.save');
        Route::post('/{uuid}/submit', [$controller, 'submit'])->name('submit');
        Route::post('/{uuid}/complete', [$controller, 'complete'])->name('complete');
    });
