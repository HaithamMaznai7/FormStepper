<?php

declare(strict_types=1);

namespace HaithamMaznai\FormStepper\Schema;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use InvalidArgumentException;
use JsonSerializable;

/**
 * @template TDefinition of array
 *
 * @implements Arrayable<array-key, mixed>
 */
abstract class Definition implements Arrayable, Jsonable, JsonSerializable
{
    /** @param TDefinition $definition */
    protected function __construct(protected readonly array $definition)
    {
        $this->assertJsonValues($definition);
        json_encode($definition, JSON_THROW_ON_ERROR);
    }

    /** @return TDefinition */
    public function toArray(): array
    {
        return $this->definition;
    }

    public function toJson($options = 0): string
    {
        return json_encode($this->definition, $options | JSON_THROW_ON_ERROR);
    }

    /** @return TDefinition */
    public function jsonSerialize(): array
    {
        return $this->definition;
    }

    private function assertJsonValues(mixed $value): void
    {
        if (is_array($value)) {
            foreach ($value as $child) {
                $this->assertJsonValues($child);
            }

            return;
        }

        if ($value !== null && ! is_scalar($value)) {
            throw new InvalidArgumentException('Stored schema definitions must contain only JSON-compatible arrays and scalar values.');
        }
    }
}
