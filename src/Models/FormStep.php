<?php

declare(strict_types=1);

namespace HaithamMaznai\FormStepper\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $form_id
 * @property string $step_key
 * @property array<string, mixed> $values
 * @property-read Form $form
 */
class FormStep extends Model
{
    protected $fillable = [
        'form_id',
        'step_key',
        'values',
        'saved_at',
    ];

    protected $casts = [
        'values' => 'array',
        'saved_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return (string) config('form-stepper.tables.steps', 'form_steps');
    }

    /** @return BelongsTo<Form, $this> */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    /**
     * @param  Builder<FormStep>  $query
     * @return Builder<FormStep>
     */
    public function scopeRequireds(Builder $query): Builder
    {
        return $query->whereNull('saved_at');
    }

    /**
     * @param  Builder<FormStep>  $query
     * @return Builder<FormStep>
     */
    public function scopePasseds(Builder $query): Builder
    {
        return $query->whereNotNull('saved_at');
    }
}
