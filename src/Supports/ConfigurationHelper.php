<?php

declare(strict_types=1);

namespace FormStepper\FormStepper\Supports;

use UnitEnum;

class ConfigurationHelper
{
    public static function isEnabled(): bool
    {
        return config('form-stepper.enabled', false);
    }

    public static function isFormTypeEnabled(): bool
    {
        return config('form-stepper.types', null) !== null;
    }

    public static function isFormTypeEnumerated(): bool
    {
        return self::isFormTypeEnabled() && class_exists(config('form-stepper.types-enum', null));
    }

    /** @return array<array-key, string|UnitEnum> */
    public static function getFormTypes(): array
    {
        if (self::isFormTypeEnumerated()) {
            return class_exists(config('form-stepper.types', null)) ? config('form-stepper.types-enum', null)::cases() : [];
        } elseif (is_array(config('form-stepper.types', ['b2c', 'b2b']))) {
            return config('form-stepper.types', ['b2c', 'b2b']);
        } else {
        }

        return config('form-stepper.types', null);
    }

    public static function getConfigValue(string $key, mixed $default = null): mixed
    {
        return config('form-stepper.'.$key, $default);
    }
}
