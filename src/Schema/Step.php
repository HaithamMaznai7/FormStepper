<?php

declare(strict_types=1);

namespace HaithamMaznai\FormStepper\Schema;

use HaithamMaznai\FormStepper\Support\SchemaNormalizer;

/** @extends Definition<array<string, mixed>> */
final class Step extends Definition
{
    /**
     * @param  list<Input>  $inputs
     * @param  array<string, mixed>  $attributes
     */
    public static function make(string $key, array $inputs = [], array $attributes = []): self
    {
        return self::fromArray([
            ...$attributes,
            'key' => $key,
            'requirements' => array_map(static fn (Input $input): array => $input->toArray(), $inputs),
        ]);
    }

    /** @param array<string, mixed> $definition */
    public static function fromArray(array $definition): self
    {
        (new SchemaNormalizer)->normalize([$definition]);

        return new self($definition);
    }
}
