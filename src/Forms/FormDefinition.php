<?php

declare(strict_types=1);

namespace FormStepper\FormStepper\Forms;

use Illuminate\Contracts\Support\Arrayable;

/**
 * @implements Arrayable<string, mixed>
 */
class FormDefinition implements Arrayable
{
    /**
     * @param  list<array<string, mixed>>  $steps
     */
    public function __construct(
        public readonly string $type,
        public readonly string $mode,
        public readonly array $steps,
        public readonly bool $hasAuthenticationRequiredSteps,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'mode' => $this->mode,
            'steps' => $this->steps,
            'has_authentication_required_steps' => $this->hasAuthenticationRequiredSteps,
        ];
    }
}
