<?php

declare(strict_types=1);

namespace HaithamMaznai\FormStepper\Interfaces;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use UnitEnum;

interface Requester
{
    /** @return MorphMany<Model, Model&$this> */
    public function requests(): MorphMany;

    public function getDefaultRequestType(): mixed;

    /** @return array<array-key, string|UnitEnum> */
    public function getAvailableRequestTypes(): array;

    public function getObjectType(): string;

    public function getObjectKey(): mixed;
}
