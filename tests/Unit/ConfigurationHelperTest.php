<?php

declare(strict_types=1);

use FormStepper\FormStepper\Supports\ConfigurationHelper;

it('is true', function () {
    // info(config('form-stepper.enabled', false));
    expect(ConfigurationHelper::isEnabled())->toBeTrue();
});
