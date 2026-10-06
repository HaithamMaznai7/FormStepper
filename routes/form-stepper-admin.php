<?php

declare(strict_types=1);

use HaithamMaznai\FormStepper\Http\Controllers\Admin\FormController;
use HaithamMaznai\FormStepper\Http\Controllers\Admin\InputController;
use HaithamMaznai\FormStepper\Http\Controllers\Admin\InputTypeController;
use HaithamMaznai\FormStepper\Http\Controllers\Admin\StepTemplateController;
use Illuminate\Support\Facades\Route;

$options = config('form-stepper.admin', []);

if (! ($options['enabled'] ?? false)) {
    return;
}

$controllers = [
    'input_types' => InputTypeController::class,
    'inputs' => InputController::class,
    'steps' => StepTemplateController::class,
    'forms' => FormController::class,
    ...($options['controllers'] ?? []),
];
$middleware = $options['middleware'] ?? ['web', 'auth'];

if (is_string($options['gate'] ?? null) && $options['gate'] !== '') {
    $middleware[] = 'can:'.$options['gate'];
}

Route::prefix($options['prefix'] ?? 'form-stepper/admin')
    ->middleware($middleware)
    ->name($options['name'] ?? 'form-stepper.admin.')
    ->group(function () use ($controllers, $options): void {
        Route::redirect('/', '/'.trim((string) ($options['prefix'] ?? 'form-stepper/admin'), '/').'/forms')->name('home');
        Route::resource('input-types', $controllers['input_types'])->parameters(['input-types' => 'inputType']);
        Route::resource('inputs', $controllers['inputs'])->parameters(['inputs' => 'input']);
        Route::resource('steps', $controllers['steps'])->parameters(['steps' => 'step']);
        Route::resource('forms', $controllers['forms'])->parameters(['forms' => 'form:uuid']);
        Route::put('forms/{form:uuid}/steps/{stepKey}', [$controllers['forms'], 'updateStep'])->name('forms.steps.update');
    });
