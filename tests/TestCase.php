<?php

declare(strict_types=1);

namespace FormStepper\FormStepper\Tests;

use FormStepper\FormStepper\FormStepperServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            FormStepperServiceProvider::class,
        ];
    }
}
