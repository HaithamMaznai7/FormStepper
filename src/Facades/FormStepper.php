<?php

declare(strict_types=1);

namespace HaithamMaznai\FormStepper\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \HaithamMaznai\FormStepper\FormStepper
 */
class FormStepper extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \HaithamMaznai\FormStepper\FormStepper::class;
    }
}
