<?php

declare(strict_types=1);

namespace HaithamMaznai\FormStepper\Models;

use HaithamMaznai\FormStepper\Schema\Input;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use InvalidArgumentException;

/**
 * A reusable lookup input. Options and library steps take a snapshot with `toInput()`.
 *
 * @property int $id
 * @property string $key
 * @property string|null $label
 * @property string $type
 * @property list<mixed>|null $rules
 * @property string|null $placeholder
 * @property mixed $default_value
 * @property list<array{value: string, label: string}>|null $options
 * @property array<string, mixed>|null $extra
 * @property-read Collection<int, FormInput> $children
 * @property-read Collection<int, FormInput> $parents
 * @property-read Collection<int, FormStepTemplate> $stepTemplates
 * @property-read FormInputType|null $inputType
 */
class FormInput extends Model
{
    protected $fillable = [
        'key',
        'label',
        'type',
        'rules',
        'placeholder',
        'default_value',
        'options',
        'extra',
    ];

    protected function casts(): array
    {
        return [
            'rules' => 'array',
            'default_value' => 'json',
            'options' => 'array',
            'extra' => 'array',
        ];
    }

    public function getTable(): string
    {
        return (string) config('form-stepper.tables.inputs', 'form_inputs');
    }

    /** @return BelongsTo<FormInputType, $this> */
    public function inputType(): BelongsTo
    {
        return $this->belongsTo(FormInputType::class, 'type', 'key');
    }

    /** @return BelongsToMany<FormInput, $this> */
    public function children(): BelongsToMany
    {
        return $this->belongsToMany(
            FormInput::class,
            (string) config('form-stepper.tables.input_children', 'form_input_children'),
            'parent_id',
            'child_id',
        )->withPivot('position')->orderByPivot('position');
    }

    /** @return BelongsToMany<FormInput, $this> */
    public function parents(): BelongsToMany
    {
        return $this->belongsToMany(
            FormInput::class,
            (string) config('form-stepper.tables.input_children', 'form_input_children'),
            'child_id',
            'parent_id',
        );
    }

    /** @return BelongsToMany<FormStepTemplate, $this> */
    public function stepTemplates(): BelongsToMany
    {
        return $this->belongsToMany(
            FormStepTemplate::class,
            (string) config('form-stepper.tables.step_template_inputs', 'form_steps_library_inputs'),
            'input_id',
            'step_id',
        );
    }

    /**
     * Replace complex children in the given order.
     *
     * @param  list<int>  $childIds
     */
    public function syncChildren(array $childIds): void
    {
        foreach ($childIds as $childId) {
            $child = self::query()->find($childId);

            if ($childId === $this->getKey() || $child?->containsInput((int) $this->getKey())) {
                throw new InvalidArgumentException('A complex input cannot contain itself.');
            }
        }

        $this->children()->sync(collect($childIds)->values()->mapWithKeys(
            static fn (int $id, int $position): array => [$id => ['position' => $position]],
        )->all());
    }

    /**
     * Whether this input contains the given input anywhere in its child tree.
     *
     * @param  list<int>  $visited
     */
    public function containsInput(int $inputId, array $visited = []): bool
    {
        if (in_array((int) $this->getKey(), $visited, true)) {
            return false;
        }

        $visited[] = (int) $this->getKey();

        foreach ($this->children as $child) {
            if ($child->getKey() === $inputId || $child->containsInput($inputId, $visited)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Snapshot this lookup input as a schema input.
     *
     * @param  list<int>  $ancestors
     */
    public function toInput(array $ancestors = []): Input
    {
        if (in_array((int) $this->getKey(), $ancestors, true)) {
            throw new InvalidArgumentException("Input [{$this->key}] contains itself.");
        }

        $extra = $this->extra ?? [];

        if (($this->options ?? []) !== []) {
            $extra['options'] = $this->options;
        }

        $attributes = array_filter([
            'label' => $this->label,
            'placeholder' => $this->placeholder,
            'rules' => $this->rules ?? [],
            'extra' => $extra === [] ? null : $extra,
        ], static fn (mixed $value): bool => $value !== null);

        if ($this->default_value !== null) {
            $attributes['value'] = $this->default_value;
        }

        if ($this->type === 'complex') {
            $ancestors[] = (int) $this->getKey();

            return Input::complex(
                $this->key,
                array_values($this->children->map(static fn (FormInput $child): Input => $child->toInput($ancestors))->all()),
                $attributes,
            );
        }

        return Input::make($this->key, $this->type, $attributes);
    }
}
