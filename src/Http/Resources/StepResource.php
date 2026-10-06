<?php

declare(strict_types=1);

namespace HaithamMaznai\FormStepper\Http\Resources;

use HaithamMaznai\FormStepper\Support\RuleCollector;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

/**
 * Formats one step (or single-mode container).
 *
 * Payload: `['definition' => array<string, mixed>, 'values' => array<string, mixed>|null]`.
 *
 * @property array{definition: array<string, mixed>, values: array<string, mixed>|null} $resource
 */
class StepResource extends FormStepperResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $step = $this->definition();
        $values = $this->values();
        $requirements = $step['requirements'] ?? [];
        $repeatable = (bool) ($step['repeatable'] ?? false);

        $data = [
            ...Arr::except($step, ['requirements']),
            'rules' => RuleCollector::forStep($step),
            'values' => $values,
            'requirements' => $this->resolveCollection(
                'requirement',
                array_map(
                    static fn (array $requirement): array => [
                        'definition' => $requirement,
                        'value' => $repeatable ? null : Arr::get($values ?? [], $requirement['key']),
                    ],
                    $requirements,
                ),
                $request,
            ),
        ];

        if ($repeatable) {
            $instances = $values[$step['repeat_name']] ?? [];

            $data['repeats'] = $this->resolveCollection(
                'repeatable',
                array_map(
                    static fn (int $index, mixed $instance): array => [
                        'step' => $step,
                        'index' => $index,
                        'values' => is_array($instance) ? $instance : [],
                    ],
                    array_keys(is_array($instances) ? $instances : []),
                    is_array($instances) ? $instances : [],
                ),
                $request,
            );
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return $this->resource['definition'];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function values(): ?array
    {
        return $this->resource['values'];
    }
}
