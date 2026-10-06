<?php

declare(strict_types=1);

namespace HaithamMaznai\FormStepper\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property int $form_id
 * @property string $option_key
 * @property-read Form $form
 * @property-read Model|null $option
 */
class FormOption extends Model
{
    protected $fillable = [
        'form_id',
        'option_type',
        'option_id',
        'option_key',
    ];

    public function getTable(): string
    {
        return (string) config('form-stepper.tables.options', 'form_options');
    }

    /** @return BelongsTo<Form, $this> */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    /** @return MorphTo<Model, $this> */
    public function option(): MorphTo
    {
        return $this->morphTo();
    }
}
