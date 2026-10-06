<?php

declare(strict_types=1);

namespace FormStepper\FormStepper\Schema;

use FormStepper\FormStepper\Support\SchemaNormalizer;

/** @extends Definition<array<string, mixed>> */
final class Input extends Definition
{
    /** @param array<string, mixed> $attributes */
    public static function make(string $key, string $type = 'input', array $attributes = []): self
    {
        return self::fromArray([...$attributes, 'key' => $key, 'type' => $type]);
    }

    /**
     * @param  list<Input>  $children
     * @param  array<string, mixed>  $attributes
     */
    public static function complex(string $key, array $children, array $attributes = []): self
    {
        return self::make($key, 'complex', [
            ...$attributes,
            'requirements' => array_map(static fn (Input $input): array => $input->toArray(), $children),
        ]);
    }

    /** @param array<string, mixed> $definition */
    public static function fromArray(array $definition): self
    {
        (new SchemaNormalizer)->normalize([['key' => 'input-definition', 'requirements' => [$definition]]]);

        return new self($definition);
    }
}
