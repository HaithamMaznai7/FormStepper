<?php

declare(strict_types=1);

namespace HaithamMaznai\FormStepper\Tests;

use HaithamMaznai\FormStepper\FormStepperServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
    }

    protected function getPackageProviders($app): array
    {
        return [
            FormStepperServiceProvider::class,
        ];
    }
}
