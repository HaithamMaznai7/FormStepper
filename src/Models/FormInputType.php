<?php

declare(strict_types=1);

namespace HaithamMaznai\FormStepper\Models;

use HaithamMaznai\FormStepper\Support\InputTypeRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * @property int $id
 * @property string $key
 * @property string $name
 * @property string|null $description
 * @property bool $is_system
 * @property bool $has_children
 * @property bool $has_options
 */
class FormInputType extends Model
{
    protected $fillable = [
        'key',
        'name',
        'description',
        'is_system',
        'has_children',
        'has_options',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'has_children' => 'boolean',
            'has_options' => 'boolean',
        ];
    }

    public function getTable(): string
    {
        return (string) config('form-stepper.tables.input_types', 'form_input_types');
    }

    protected static function booted(): void
    {
        static::updating(static function (FormInputType $type): void {
            if ($type->getOriginal('is_system') && $type->isDirty(['key', 'is_system', 'has_children'])) {
                throw new LogicException('System input types cannot change their key or behaviour.');
            }
        });

        static::deleting(static function (FormInputType $type): void {
            if ($type->is_system) {
                throw new LogicException('System input types cannot be deleted.');
            }
        });

        static::saved(static fn () => InputTypeRegistry::flush());
        static::deleted(static fn () => InputTypeRegistry::flush());
    }

    /** @return HasMany<FormInput, $this> */
    public function inputs(): HasMany
    {
        return $this->hasMany(FormInput::class, 'type', 'key');
    }
}
