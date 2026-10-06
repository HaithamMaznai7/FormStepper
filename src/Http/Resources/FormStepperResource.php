<?php

declare(strict_types=1);

namespace HaithamMaznai\FormStepper\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Base for package resources. Child resources are resolved through `form-stepper.resources`
 * so applications can replace any level of the form response.
 */
abstract class FormStepperResource extends JsonResource
{
    private const array DEFAULTS = [
        'form' => FormResource::class,
        'step' => StepResource::class,
        'requirement' => RequirementResource::class,
        'repeatable' => RepeatableResource::class,
    ];

    /**
     * @return class-string<JsonResource>
     */
    public static function resourceClass(string $name): string
    {
        $class = config("form-stepper.resources.{$name}") ?? self::DEFAULTS[$name] ?? null;

        if (! is_string($class) || ! is_a($class, JsonResource::class, true)) {
            throw new InvalidArgumentException(
                "The form-stepper resource [{$name}] must be a JsonResource class.",
            );
        }

        return $class;
    }

    /**
     * Resolve items into plain arrays using the configured resource class.
     *
     * @param  iterable<mixed>  $items
     * @return Collection<int, array<array-key, mixed>>
     */
    protected function resolveCollection(string $name, iterable $items, Request $request): Collection
    {
        $class = self::resourceClass($name);

        return collect($items)
            ->map(static fn (mixed $item): array => (new $class($item))->resolve($request))
            ->values();
    }
}
