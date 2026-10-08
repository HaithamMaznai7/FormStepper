<?php

declare(strict_types=1);

namespace HaithamMaznai\FormStepper\Support;

use HaithamMaznai\FormStepper\Contracts\ProvidesFormRequirements;
use HaithamMaznai\FormStepper\Forms\FormBuilder;
use HaithamMaznai\FormStepper\Forms\FormDefinition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class SchemaAssembler
{
    public function __construct(
        private readonly SchemaNormalizer $normalizer,
        private readonly ScopeMatcher $scopeMatcher,
    ) {}

    /**
     * @param  list<ProvidesFormRequirements&Model>  $options
     */
    public function assemble(
        FormBuilder $builder,
        array $options,
        ?Model $requester,
        ?Model $tenant,
        bool $guest,
        ?string $modeOverride = null,
    ): FormDefinition {
        if (method_exists($builder, 'steps')) {
            throw new InvalidArgumentException('Replace the removed FormBuilder::steps() hook with startWithSteps() or endWithSteps().');
        }

        $mode = $modeOverride ?? $builder->mode();

        if (! in_array($mode, ['single', 'stepper'], true)) {
            throw new InvalidArgumentException("Unsupported form mode [{$mode}].");
        }

        $optionKeys = [];

        foreach ($options as $option) {
            $key = $option->formOptionKey();

            if ($key === '' || in_array($key, $optionKeys, true)) {
                throw new InvalidArgumentException("Selected option keys must be unique and non-empty [{$key}].");
            }

            $optionKeys[] = $key;
        }

        $this->assertCompatible($options, $optionKeys);

        $sources = [['group' => 0, 'steps' => $builder->startWithSteps()]];

        foreach ($options as $option) {
            $sources[] = ['group' => 1, 'steps' => $option->formSteps()];
        }

        $sources[] = ['group' => 2, 'steps' => $builder->endWithSteps()];
        $steps = [];
        $stepGroups = [];

        foreach ($sources as $source) {
            foreach ($this->normalizer->normalize($source['steps']) as $step) {
                $stepGroups[$step['key']] ??= $source['group'];
                $steps[$step['key']] = isset($steps[$step['key']])
                    ? $this->mergeStep($steps[$step['key']], $step)
                    : $step;
            }
        }

        $hasAuthenticationRequiredSteps = false;
        $visibleSteps = [];

        foreach ($steps as $step) {
            if ($step['requires_authentication']) {
                $authenticationScope = $step['scope'];
                $authenticationScope['guests'] = ['*'];
                $hasAuthenticationRequiredSteps = $hasAuthenticationRequiredSteps || $this->scopeMatcher->matches(
                    $authenticationScope,
                    $builder->formType(),
                    $requester,
                    $tenant,
                    $guest,
                );
            }

            if (! $this->scopeMatcher->matches($step['scope'], $builder->formType(), $requester, $tenant, $guest)) {
                continue;
            }

            $step['can_repeat'] = $step['repeatable'] && $this->scopeMatcher->matches(
                $step['repeatable_scope'],
                $builder->formType(),
                $requester,
                $tenant,
                $guest,
            );
            $step['requirements'] = $this->visibleRequirements(
                $step['requirements'],
                $builder->formType(),
                $requester,
                $tenant,
                $guest,
            );
            $visibleSteps[] = $step;
        }

        usort($visibleSteps, static function (array $left, array $right) use ($stepGroups): int {
            $leftPriority = $left['priority'];
            $rightPriority = $right['priority'];

            return ($stepGroups[$left['key']] <=> $stepGroups[$right['key']])
                ?: (($leftPriority < 0) <=> ($rightPriority < 0))
                ?: ($leftPriority <=> $rightPriority);
        });

        return new FormDefinition(
            $builder->formType(),
            $mode,
            $visibleSteps,
            $hasAuthenticationRequiredSteps,
        );
    }

    /**
     * @param  list<ProvidesFormRequirements&Model>  $options
     * @param  list<string>  $keys
     */
    private function assertCompatible(array $options, array $keys): void
    {
        foreach ($options as $option) {
            $missing = array_diff($option->requires(), $keys);

            if ($missing !== []) {
                throw ValidationException::withMessages([
                    'options' => 'Selected option ['.$option->formOptionKey().'] requires: '.implode(', ', $missing).'.',
                ]);
            }

            foreach ($keys as $otherKey) {
                if ($otherKey === $option->formOptionKey()) {
                    continue;
                }

                if (in_array($otherKey, $option->excludes(), true)) {
                    throw ValidationException::withMessages([
                        'options' => "Selected options [{$option->formOptionKey()}] and [{$otherKey}] are mutually exclusive.",
                    ]);
                }

                $compatible = $option->compatibleWith();

                if ($compatible !== [] && ! in_array($otherKey, $compatible, true)) {
                    throw ValidationException::withMessages([
                        'options' => "Selected option [{$option->formOptionKey()}] cannot be combined with [{$otherKey}].",
                    ]);
                }
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $requirements
     * @return list<array<string, mixed>>
     */
    private function visibleRequirements(
        array $requirements,
        string $formType,
        ?Model $requester,
        ?Model $tenant,
        bool $guest,
    ): array {
        $visible = [];

        foreach ($requirements as $requirement) {
            if (! $this->scopeMatcher->matches($requirement['scope'], $formType, $requester, $tenant, $guest)) {
                continue;
            }

            if ($requirement['type'] === 'complex') {
                $requirement['children'] = $this->visibleRequirements(
                    $requirement['children'],
                    $formType,
                    $requester,
                    $tenant,
                    $guest,
                );

                if ($requirement['children'] === []) {
                    continue;
                }
            }

            $visible[] = $requirement;
        }

        return $visible;
    }

    /**
     * @param  array<string, mixed>  $existing
     * @param  array<string, mixed>  $incoming
     * @return array<string, mixed>
     */
    private function mergeStep(array $existing, array $incoming): array
    {
        foreach (['priority', 'repeatable', 'repeat_name', 'requires_authentication'] as $key) {
            if (
                $existing[$key] !== $incoming[$key] &&
                $existing[$key] !== null &&
                $incoming[$key] !== null
            ) {
                throw new InvalidArgumentException(
                    "Conflicting definitions for step [{$existing['key']}] attribute [{$key}].",
                );
            }

            $existing[$key] ??= $incoming[$key];
        }

        $existing['scope'] = $this->mergeScopes($existing['scope'], $incoming['scope']);
        $existing['repeatable_scope'] = $this->mergeScopes(
            $existing['repeatable_scope'],
            $incoming['repeatable_scope'],
        );

        foreach ($incoming['requirements'] as $requirement) {
            $index = $this->findRequirement($existing['requirements'], $requirement['key']);

            if ($index === null) {
                $existing['requirements'][] = $requirement;

                continue;
            }

            $existing['requirements'][$index] = $this->mergeRequirement(
                $existing['requirements'][$index],
                $requirement,
            );
        }

        return $existing;
    }

    /**
     * @param  array<string, mixed>  $existing
     * @param  array<string, mixed>  $incoming
     * @return array<string, mixed>
     */
    private function mergeRequirement(array $existing, array $incoming): array
    {
        foreach (['type', 'value', 'label', 'placeholder', 'extra'] as $key) {
            if (($existing[$key] ?? null) !== ($incoming[$key] ?? null)) {
                throw new InvalidArgumentException(
                    "Conflicting definitions for input [{$existing['key']}] attribute [{$key}].",
                );
            }
        }

        $existing['rules'] = array_values(array_unique([...$existing['rules'], ...$incoming['rules']]));
        $existing['scope'] = $this->mergeScopes($existing['scope'], $incoming['scope']);

        if ($existing['type'] === 'complex') {
            foreach ($incoming['children'] as $child) {
                $index = $this->findRequirement($existing['children'], $child['key']);

                if ($index === null) {
                    $existing['children'][] = $child;

                    continue;
                }

                $existing['children'][$index] = $this->mergeRequirement($existing['children'][$index], $child);
            }
        }

        return $existing;
    }

    /**
     * @param  array{types: list<string>, tenants: list<string>, requester: list<string>, guests: list<string>}  $left
     * @param  array{types: list<string>, tenants: list<string>, requester: list<string>, guests: list<string>}  $right
     * @return array{types: list<string>, tenants: list<string>, requester: list<string>, guests: list<string>}
     */
    private function mergeScopes(array $left, array $right): array
    {
        foreach (array_keys($left) as $dimension) {
            if ($left[$dimension] === ['*'] || $right[$dimension] === ['*']) {
                $left[$dimension] = ['*'];

                continue;
            }

            $left[$dimension] = array_values(array_unique([...$left[$dimension], ...$right[$dimension]]));
        }

        return $left;
    }

    /**
     * @param  list<array<string, mixed>>  $requirements
     */
    private function findRequirement(array $requirements, string $key): ?int
    {
        foreach ($requirements as $index => $requirement) {
            if ($requirement['key'] === $key) {
                return $index;
            }
        }

        return null;
    }
}
