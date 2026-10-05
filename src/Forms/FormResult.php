<?php

declare(strict_types=1);

namespace VendorName\Skeleton\Forms;

use Illuminate\Contracts\Support\Arrayable;
use VendorName\Skeleton\Models\Form;

/**
 * @implements Arrayable<string, mixed>
 */
class FormResult implements Arrayable
{
    public function __construct(
        private readonly Form $form,
        private readonly ?string $resumeToken = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $definition = $this->form->definition ?? [];
        $steps = $definition['steps'] ?? [];

        if (($definition['mode'] ?? null) === 'stepper') {
            $steps[] = [
                'key' => 'review',
                'title' => 'Review',
                'type' => 'review',
                'computed' => true,
            ];
        }

        $values = $this->form->valuesByStep();

        $currentStep = null;

        foreach ($definition['steps'] ?? [] as $step) {
            if ($step['key'] === $this->form->current_step) {
                $currentStep = $step;

                break;
            }
        }

        return array_filter([
            'id' => $this->form->uuid,
            'type' => $this->form->type,
            'mode' => $this->form->mode,
            'status' => $this->form->status,
            'current_step' => $this->form->current_step,
            'requires_authentication' => (bool) ($currentStep['requires_authentication'] ?? false),
            'authentication_required' => $this->form->requester === null &&
                (bool) ($definition['has_authentication_required_steps'] ?? false),
            'definition' => [
                ...$definition,
                'steps' => $steps,
            ],
            'selected_options' => $this->form->selectedOptions->pluck('option_key')->all(),
            'values' => $values,
            'completed_at' => $this->form->completed_at?->toISOString(),
            'resume_token' => $this->resumeToken,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
