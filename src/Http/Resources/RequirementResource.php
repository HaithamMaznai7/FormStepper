<?php

declare(strict_types=1);

namespace HaithamMaznai\FormStepper\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;

/**
 * Formats one input requirement with its rules and saved value.
 *
 * Payload: `['definition' => array<string, mixed>, 'value' => mixed]`.
 *
 * @property array{definition: array<string, mixed>, value: mixed} $resource
 */
class RequirementResource extends FormStepperResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $requirement = $this->definition();
        $value = $this->value();

        $data = [
            ...Arr::except($requirement, ['children', 'value']),
            'rules' => $requirement['rules'] ?? [],
            'default' => $requirement['value'] ?? null,
            'value' => $value,
        ];

        if (($requirement['type'] ?? null) === 'complex') {
            $data['children'] = $this->resolveCollection(
                'requirement',
                array_map(
                    static fn (array $child): array => [
                        'definition' => $child,
                        'value' => is_array($value) ? Arr::get($value, $child['key']) : null,
                    ],
                    $requirement['children'] ?? [],
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

    public function value(): mixed
    {
        return $this->resource['value'];
    }
}
