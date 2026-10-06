<?php

declare(strict_types=1);

use HaithamMaznai\FormStepper\FormStepper;
use Illuminate\Support\Facades\Route;

it('resolves the singleton', function () {
    expect(app(FormStepper::class))->toBeInstanceOf(FormStepper::class);
});

it('returns the same instance from the container', function () {
    expect(app(FormStepper::class))->toBe(app(FormStepper::class));
});

it('registers the form API routes', function () {
    expect(Route::has('form-stepper.forms.store'))->toBeTrue()
        ->and(Route::has('form-stepper.forms.steps.save'))->toBeTrue();
});
it('merges the package config', function () {
    expect(config('form-stepper.placeholder'))->toBe('default')
        ->and(config('form-stepper.default_mode'))->toBe('stepper')
        ->and(config('form-stepper.tables.forms'))->toBe('forms');
});
it('loads the package translations', function () {
    expect(trans('form-stepper::messages.placeholder'))->toBe('FormStepper placeholder translation.');
});
it('loads the package views', function () {
    expect(view()->exists('form-stepper::placeholder'))->toBeTrue();
});
it('registers the artisan command', function () {
    $this->artisan('form-stepper:placeholder')
        ->expectsOutputToContain('FormStepper placeholder command executed.')
        ->assertSuccessful();
});
