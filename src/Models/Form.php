<?php

declare(strict_types=1);

namespace FormStepper\FormStepper\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property string $type
 * @property string $mode
 * @property string $status
 * @property string|null $current_step_id
 * @property array<string, mixed> $definition
 * @property string|null $resume_token_hash
 * @property Carbon|null $completed_at
 * @property-read Model|null $requester
 * @property-read Model|null $tenant
 * @property-read Collection<int, FormOption> $selectedOptions
 * @property-read Collection<int, FormStep> $steps
 */
class Form extends Model
{
    protected $fillable = [
        'uuid',
        'type',
        'mode',
        'requester_type',
        'requester_id',
        'tenant_type',
        'tenant_id',
        'status',
        'current_step_id',
        'definition',
        'resume_token_hash',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'definition' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function getTable(): string
    {
        return (string) config('form-stepper.tables.forms', 'forms');
    }

    /** @return MorphTo<Model, $this> */
    public function requester(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return MorphTo<Model, $this> */
    public function tenant(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return HasMany<FormOption, $this> */
    public function selectedOptions(): HasMany
    {
        return $this->hasMany(FormOption::class);
    }

    /** @return HasMany<FormStep, $this> */
    public function steps(): HasMany
    {
        return $this->hasMany(FormStep::class);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function valuesByStep(): array
    {
        $values = [];

        foreach ($this->steps as $step) {
            $values[$step->step_key] = $step->values;
        }

        return $values;
    }
}
