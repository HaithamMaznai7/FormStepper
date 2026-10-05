<?php

declare(strict_types=1);

use FormStepper\FormStepper\Http\Controllers\FormController;
use Illuminate\Support\Facades\Route;

$options = config('form-stepper.routes', []);

if (! ($options['enabled'] ?? true)) {
    return;
}

Route::prefix($options['prefix'] ?? 'api/forms')
    ->middleware($options['middleware'] ?? ['api'])
    ->name($options['name'] ?? 'form-stepper.forms.')
    ->group(function (): void {
        Route::get('/', [FormController::class, 'index'])->name('index');
        Route::post('/', [FormController::class, 'store'])->name('store');
        Route::get('/{uuid}', [FormController::class, 'show'])->name('show');
        Route::patch('/{uuid}/options', [FormController::class, 'updateOptions'])->name('options.update');
        Route::put('/{uuid}/steps/{step}', [FormController::class, 'saveStep'])->name('steps.save');
        Route::post('/{uuid}/submit', [FormController::class, 'submit'])->name('submit');
        Route::post('/{uuid}/complete', [FormController::class, 'complete'])->name('complete');
    });
