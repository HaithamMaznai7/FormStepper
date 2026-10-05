<?php

declare(strict_types=1);

use FormStepper\FormStepper\FormStepper;

it('resolves the singleton', function () {
    expect(app(FormStepper::class))->toBeInstanceOf(FormStepper::class);
});

it('returns the same instance from the container', function () {
    expect(app(FormStepper::class))->toBe(app(FormStepper::class));
});

it('merges the package config', function () {
    expect(config('form-stepper.placeholder'))->toBe('default');
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
