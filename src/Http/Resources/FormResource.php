<?php

declare(strict_types=1);

namespace HaithamMaznai\FormStepper\Http\Resources;

use HaithamMaznai\FormStepper\Models\Form;
use Illuminate\Http\Request;

/**
 * Formats a persisted form. Single-mode forms expose their groups as `containers`.
 *
 * @property Form $resource
 */
class FormResource extends FormStepperResource
{
    private ?string $resumeToken = null;

    public function withResumeToken(?string $resumeToken): static
    {
        $this->resumeToken = $resumeToken;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $form = $this->resource;
        $definition = $form->definition ?? [];
        $steps = $definition['steps'] ?? [];
        $isSingle = $form->mode === 'single';
        $values = $form->valuesByStep();

        if (! $isSingle && ($definition['mode'] ?? null) === 'stepper') {
            $steps[] = [
                'key' => 'review',
                'title' => 'Review',
                'type' => 'review',
                'computed' => true,
            ];
        }

        $currentStep = null;

        foreach ($definition['steps'] ?? [] as $step) {
            if ($step['key'] === $form->current_step_id) {
                $currentStep = $step;

                break;
            }
        }

        $groups = $this->resolveCollection(
            'step',
            array_map(
                static fn (array $step): array => [
                    'definition' => $step,
                    'values' => $values[$step['key']] ?? null,
                ],
                $steps,
            ),
            $request,
        );

        unset($definition['steps']);
        $definition[$isSingle ? 'containers' : 'steps'] = $groups;

        return collect([
            'id' => $form->uuid,
            'type' => $form->type,
            'mode' => $form->mode,
            'status' => $form->status,
            'current_step_id' => $isSingle ? null : $form->current_step_id,
            'requires_authentication' => $isSingle
                ? null
                : (bool) ($currentStep['requires_authentication'] ?? false),
            'authentication_required' => $form->requester === null &&
                (bool) ($definition['has_authentication_required_steps'] ?? false),
            'definition' => collect($definition),
            'selected_options' => $form->selectedOptions->pluck('option_key'),
            'values' => $values,
            'completed_at' => $form->completed_at?->toISOString(),
            'resume_token' => $this->resumeToken,
        ])->reject(static fn (mixed $value): bool => $value === null)->all();
    }
}
