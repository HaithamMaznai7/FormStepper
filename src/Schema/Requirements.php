<?php

declare(strict_types=1);

namespace HaithamMaznai\FormStepper\Schema;

use InvalidArgumentException;

/** @extends Definition<list<array<string, mixed>>> */
final class Requirements extends Definition
{
    public static function make(Step ...$steps): self
    {
        return self::fromArray(array_map(static fn (Step $step): array => $step->toArray(), $steps));
    }

    /** @param array<array-key, mixed> $definitions */
    public static function fromArray(array $definitions): self
    {
        if (! array_is_list($definitions)) {
            throw new InvalidArgumentException('Option requirements must be a list of step definitions.');
        }

        $steps = [];
        $keys = [];

        foreach ($definitions as $definition) {
            if (! is_array($definition)) {
                throw new InvalidArgumentException('Every step definition must be an object.');
            }

            $step = Step::fromArray($definition)->toArray();

            if (in_array($step['key'], $keys, true)) {
                throw new InvalidArgumentException("Duplicate step key [{$step['key']}] in option requirements.");
            }

            $keys[] = $step['key'];
            $steps[] = $step;
        }

        return new self($steps);
    }

    public static function fromJson(string $json): self
    {
        $decoded = json_decode($json, flags: JSON_THROW_ON_ERROR);

        if (! is_array($decoded)) {
            throw new InvalidArgumentException('Option requirements JSON must be a list of step definitions.');
        }

        return self::fromArray(json_decode($json, associative: true, flags: JSON_THROW_ON_ERROR));
    }
}
