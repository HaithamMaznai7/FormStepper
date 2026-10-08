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
        unset($definition['title'], $definition['subtitle']);
        $definition['priority'] ??= 100;
        (new SchemaNormalizer)->normalize([$definition]);

        if (array_key_exists('requirements', $definition)) {
            $definition['requirements'] = array_map(
                static fn (array $input): array => Input::fromArray($input)->toArray(),
                $definition['requirements'],
            );
        }

        return new self($definition);
    }
}
