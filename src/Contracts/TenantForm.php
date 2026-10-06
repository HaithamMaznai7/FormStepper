<?php

declare(strict_types=1);

namespace HaithamMaznai\FormStepper\Contracts;

use HaithamMaznai\FormStepper\Models\Form;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

interface TenantForm
{
    /** @return MorphMany<Form, Model&$this> */
    public function forms(): MorphMany;
}
