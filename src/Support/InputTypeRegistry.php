<?php

declare(strict_types=1);

namespace HaithamMaznai\FormStepper\Support;

use HaithamMaznai\FormStepper\Models\FormInputType;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

/**
 * Knows which input types schemas may use: the built-in system types plus custom
 * types stored in the input types table.
 */
class InputTypeRegistry
{
    /**
     * @var list<array{key: string, name: string, description: string, has_children: bool, has_options: bool}>
     */
    public const array SYSTEM_TYPES = [
        ['key' => 'input', 'name' => 'Text', 'description' => 'Free text input.', 'has_children' => false, 'has_options' => false],
        ['key' => 'selection', 'name' => 'Selection', 'description' => 'Pick one value from options.', 'has_children' => false, 'has_options' => true],
        ['key' => 'single-selection', 'name' => 'Single selection', 'description' => 'Pick exactly one value from options.', 'has_children' => false, 'has_options' => true],
        ['key' => 'multiple-selection', 'name' => 'Multiple selection', 'description' => 'Pick many values from options.', 'has_children' => false, 'has_options' => true],
        ['key' => 'radio', 'name' => 'Radio', 'description' => 'Radio buttons for one value.', 'has_children' => false, 'has_options' => true],
        ['key' => 'checkbox', 'name' => 'Checkbox', 'description' => 'Checkboxes for one or many values.', 'has_children' => false, 'has_options' => true],
        ['key' => 'boolean', 'name' => 'Boolean', 'description' => 'Yes / no toggle.', 'has_children' => false, 'has_options' => false],
        ['key' => 'plate', 'name' => 'Plate', 'description' => 'Vehicle plate input.', 'has_children' => false, 'has_options' => false],
        ['key' => 'complex', 'name' => 'Complex', 'description' => 'Groups several inputs inside one input.', 'has_children' => true, 'has_options' => false],
    ];

    /** @var list<string>|null */
    private static ?array $customTypes = null;

    /**
     * @return list<string>
     */
    public static function systemKeys(): array
    {
        return array_column(self::SYSTEM_TYPES, 'key');
    }

    public static function supports(string $type): bool
    {
        return in_array($type, self::systemKeys(), true) || in_array($type, self::customKeys(), true);
    }

    /**
     * @return list<string>
     */
    public static function customKeys(): array
    {
        if (self::$customTypes !== null) {
            return self::$customTypes;
        }

        if (! app()->bound('db')) {
            return [];
        }

        try {
            $model = new FormInputType;

            if (! Schema::connection($model->getConnectionName())->hasTable($model->getTable())) {
                return self::$customTypes = [];
            }

            /** @var list<string> $keys */
            $keys = FormInputType::query()->where('is_system', false)->pluck('key')->all();

            return self::$customTypes = $keys;
        } catch (QueryException) {
            return [];
        }
    }

    public static function flush(): void
    {
        self::$customTypes = null;
    }
}
