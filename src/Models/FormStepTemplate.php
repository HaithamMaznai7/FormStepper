<?php

declare(strict_types=1);

namespace HaithamMaznai\FormStepper\Models;

use HaithamMaznai\FormStepper\Schema\Input;
use HaithamMaznai\FormStepper\Schema\Step;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A reusable library step built from lookup inputs. Options snapshot it with `toStep()`.
 *
 * @property int $id
 * @property string $key
 * @property int $priority
 * @property bool $repeatable
 * @property string|null $repeat_name
 * @property bool $requires_authentication
 * @property-read Collection<int, FormInput> $inputs
 */
class FormStepTemplate extends Model
{
    protected $fillable = [
        'key',
        'priority',
        'repeatable',
        'repeat_name',
        'requires_authentication',
    ];

    protected $casts = [
        'priority' => 'integer',
        'repeatable' => 'boolean',
        'requires_authentication' => 'boolean',
    ];

    protected $attributes = ['priority' => 100];

    public function getTable(): string
    {
        return (string) config('form-stepper.tables.step_templates', 'form_steps_library');
    }

    /** @return BelongsToMany<FormInput, $this> */
    public function inputs(): BelongsToMany
    {
        return $this->belongsToMany(
            FormInput::class,
            (string) config('form-stepper.tables.step_template_inputs', 'form_steps_library_inputs'),
            'step_id',
            'input_id',
        )->withPivot('position')->orderByPivot('position');
    }

    /**
     * Replace the step inputs in the given order.
     *
     * @param  list<int>  $inputIds
     */
    public function syncInputs(array $inputIds): void
    {
        $this->inputs()->sync(collect($inputIds)->values()->mapWithKeys(
            static fn (int $id, int $position): array => [$id => ['position' => $position]],
        )->all());
    }

    /**
     * Snapshot this library step as a schema step.
     *
     * @param  array<string, mixed>  $attributes  Extra step attributes such as `scope`.
     */
    public function toStep(array $attributes = []): Step
    {
        return Step::make(
            $this->key,
            array_values($this->inputs->map(static fn (FormInput $input): Input => $input->toInput())->all()),
            [
                ...array_filter([
                    'repeat-name' => $this->repeat_name,
                ], static fn (mixed $value): bool => $value !== null),
                'repeatable' => $this->repeatable,
                'priority' => $this->priority,
                'requires-authentication' => $this->requires_authentication,
                ...$attributes,
            ],
        );
    }
}
