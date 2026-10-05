<?php

declare(strict_types=1);

namespace FormStepper\FormStepper\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \FormStepper\FormStepper\FormStepper
 */
class FormStepper extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \FormStepper\FormStepper\FormStepper::class;
    }
}
