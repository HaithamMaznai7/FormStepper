<?php

declare(strict_types=1);

namespace HaithamMaznai\FormStepper\Http\Resources;

use HaithamMaznai\FormStepper\Support\RuleCollector;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

/**
 * Formats one saved instance of a repeatable step.
 *
 * Payload: `['step' => array<string, mixed>, 'index' => int, 'values' => array<string, mixed>]`.
 *
 * @property array{step: array<string, mixed>, index: int, values: array<string, mixed>} $resource
 */
class RepeatableResource extends FormStepperResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $step = $this->step();
        $values = $this->values();
        $requirements = $step['requirements'] ?? [];

        return [
            'index' => $this->index(),
            'rules' => RuleCollector::collect($requirements),
            'values' => $values,
            'requirements' => $this->resolveCollection(
                'requirement',
                array_map(
                    static fn (array $requirement): array => [
                        'definition' => $requirement,
                        'value' => Arr::get($values, $requirement['key']),
                    ],
                    $requirements,
                ),
                $request,
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function step(): array
    {
        return $this->resource['step'];
    }

    public function index(): int
    {
        return $this->resource['index'];
    }

    /**
     * @return array<string, mixed>
     */
    public function values(): array
    {
        return $this->resource['values'];
    }
}
