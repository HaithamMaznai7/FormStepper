<?php

declare(strict_types=1);

namespace HaithamMaznai\FormStepper\Support;

use InvalidArgumentException;

class SchemaNormalizer
{
    /**
     * @param  list<array<string, mixed>>  $steps
     * @return list<array<string, mixed>>
     */
    public function normalize(array $steps): array
    {
        return array_map(
            fn (array $step): array => $this->normalizeStep($step),
            $steps,
        );
    }

    /**
     * @param  array<string, mixed>  $step
     * @return array<string, mixed>
     */
    private function normalizeStep(array $step): array
    {
        $key = $this->requiredKey($step, 'step');
        $requirements = $step['requirements'] ?? [];

        if (! is_array($requirements) || ! array_is_list($requirements)) {
            throw new InvalidArgumentException("The requirements for step [{$key}] must be a list.");
        }

        $normalizedRequirements = [];
        $requirementKeys = [];

        foreach ($requirements as $requirement) {
            if (! is_array($requirement)) {
                throw new InvalidArgumentException("A requirement in step [{$key}] must be an object.");
            }

            $normalized = $this->normalizeRequirement($requirement, $key);

            if (in_array($normalized['key'], $requirementKeys, true)) {
                throw new InvalidArgumentException(
                    "Duplicate requirement key [{$normalized['key']}] in step [{$key}].",
                );
            }

            $requirementKeys[] = $normalized['key'];
            $normalizedRequirements[] = $normalized;
        }

        return [
            'key' => $key,
            'title' => $step['title'] ?? null,
            'subtitle' => $step['subtitle'] ?? null,
            'repeatable' => (bool) ($step['repeatable'] ?? false),
            'repeat_name' => $step['repeat-name'] ?? $step['repeat_name'] ?? null,
            'requires_authentication' => (bool) ($step['requires-authentication'] ?? $step['requires_authentication'] ?? false),
            'scope' => $this->scope($step),
            'repeatable_scope' => $this->repeatableScope($step),
            'requirements' => $normalizedRequirements,
        ];
    }

    /**
     * @param  array<string, mixed>  $requirement
     * @return array<string, mixed>
     */
    private function normalizeRequirement(array $requirement, string $stepKey): array
    {
        $key = $this->requiredKey($requirement, "requirement in step [{$stepKey}]");
        $type = $requirement['type'] ?? null;

        if (! is_string($type) || ! $this->supportsInputType($type)) {
            throw new InvalidArgumentException(
                "Unsupported input type for requirement [{$key}] in step [{$stepKey}].",
            );
        }

        $rules = $requirement['rules'] ?? [];

        if (! is_array($rules) || ! array_is_list($rules)) {
            throw new InvalidArgumentException("Validation rules for input [{$key}] must be a list.");
        }

        $normalized = [
            'key' => $key,
            'type' => $type,
            'rules' => $rules,
            'scope' => $this->scope($requirement),
        ];

        foreach (['value', 'label', 'placeholder', 'extra'] as $attribute) {
            if (array_key_exists($attribute, $requirement)) {
                $normalized[$attribute] = $requirement[$attribute];
            }
        }

        if ($type === 'complex') {
            $children = $requirement['requirements'] ?? $requirement['children'] ?? [];

            if (! is_array($children) || ! array_is_list($children)) {
                throw new InvalidArgumentException("Children for complex input [{$key}] must be a list.");
            }

            $childKeys = [];
            $normalized['children'] = [];

            foreach ($children as $child) {
                if (! is_array($child)) {
                    throw new InvalidArgumentException("A child of complex input [{$key}] must be an object.");
                }

                $normalizedChild = $this->normalizeRequirement($child, $stepKey);

                if (in_array($normalizedChild['key'], $childKeys, true)) {
                    throw new InvalidArgumentException(
                        "Duplicate child key [{$normalizedChild['key']}] in complex input [{$key}].",
                    );
                }

                $childKeys[] = $normalizedChild['key'];
                $normalized['children'][] = $normalizedChild;
            }
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array{types: list<string>, tenants: list<string>, requester: list<string>, guests: list<string>}
     */
    private function scope(array $definition): array
    {
        $scopes = is_array($definition['scope'] ?? null) ? $definition['scope'] : [];

        $requesterScope = $this->firstPresent(
            $definition,
            ['requester-scope', 'business-types'],
            $scopes['requester'] ?? null,
        );

        return [
            'types' => $this->scopeValues($this->firstPresent($definition, ['types-scope'], $scopes['types'] ?? null)),
            'tenants' => $this->scopeValues(
                $this->firstPresent($definition, ['tenant-types'], $scopes['tenants'] ?? null),
                true,
            ),
            'requester' => $this->scopeValues($requesterScope),
            'guests' => $this->guestScope(
                $this->firstPresent($definition, ['guest-scope'], $scopes['guests'] ?? null),
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $step
     * @return array{types: list<string>, tenants: list<string>, requester: list<string>, guests: list<string>}
     */
    private function repeatableScope(array $step): array
    {
        $repeatable = is_array($step['repeatable-scope'] ?? null)
            ? $step['repeatable-scope']
            : [];

        return [
            'types' => $this->scopeValues(
                $this->firstPresent(
                    $step,
                    ['repeatable-types-scope'],
                    $this->firstPresent($repeatable, ['types-scope', 'types'], null),
                ),
            ),
            'tenants' => $this->scopeValues(
                $this->firstPresent(
                    $step,
                    ['repeatable-tenant-types'],
                    $this->firstPresent($repeatable, ['tenant-types', 'tenants'], null),
                ),
                true,
            ),
            'requester' => $this->scopeValues(
                $this->firstPresent(
                    $step,
                    ['repeatable-requester-scope'],
                    $this->firstPresent($repeatable, ['requester-scope', 'requester'], null),
                ),
            ),
            'guests' => $this->guestScope(
                $this->firstPresent(
                    $step,
                    ['repeatable-guest-scope'],
                    $this->firstPresent($repeatable, ['guest-scope', 'guests'], null),
                ),
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  list<string>  $keys
     */
    private function firstPresent(array $definition, array $keys, mixed $default): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $definition)) {
                return $definition[$key];
            }
        }

        return $default;
    }

    /**
     * @return list<string>
     */
    private function scopeValues(mixed $scope, bool $rejectModelTypes = false): array
    {
        if ($scope === null || $scope === '*') {
            return ['*'];
        }

        if (! is_array($scope)) {
            $scope = [$scope];
        }

        if (! array_is_list($scope)) {
            throw new InvalidArgumentException('A scope must be null, a wildcard, or a list of type keys.');
        }

        $values = [];

        foreach ($scope as $value) {
            if (! is_string($value) || $value === '') {
                throw new InvalidArgumentException('Scope values must be non-empty strings.');
            }

            if ($rejectModelTypes && class_exists($value)) {
                throw new InvalidArgumentException(
                    'The tenant-types scope must contain available form-type keys, not model class names.',
                );
            }

            $values[] = $value;
        }

        return $values === [] ? ['*'] : array_values(array_unique($values));
    }

    /**
     * @return list<string>
     */
    private function guestScope(mixed $scope): array
    {
        if (is_bool($scope)) {
            return [$scope ? 'guest' : 'authenticated'];
        }

        $values = $this->scopeValues($scope);

        foreach ($values as $value) {
            if (! in_array($value, ['*', 'guest', 'authenticated'], true)) {
                throw new InvalidArgumentException(
                    'The guest-scope must contain guest, authenticated, or wildcard values.',
                );
            }
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function requiredKey(array $definition, string $description): string
    {
        $key = $definition['key'] ?? null;

        if (! is_string($key) || trim($key) === '') {
            throw new InvalidArgumentException("Every {$description} must have a non-empty key.");
        }

        return $key;
    }

    private function supportsInputType(string $type): bool
    {
        return InputTypeRegistry::supports($type);
    }
}
