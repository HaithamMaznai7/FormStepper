<?php

declare(strict_types=1);

namespace HaithamMaznai\FormStepper\Support;

class RuleCollector
{
    /**
     * Flatten requirement rules, including complex children, into Laravel validator rules.
     *
     * @param  list<array<string, mixed>>  $requirements
     * @return array<string, array<int, mixed>>
     */
    public static function collect(array $requirements, string $prefix = ''): array
    {
        $rules = [];

        foreach ($requirements as $requirement) {
            $key = $prefix.$requirement['key'];

            if (($requirement['rules'] ?? []) !== []) {
                $rules[$key] = $requirement['rules'];
            }

            if (($requirement['type'] ?? null) === 'complex') {
                $rules = [
                    ...$rules,
                    ...self::collect($requirement['children'] ?? [], $key.'.'),
                ];
            }
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $step
     * @return array<string, array<int, mixed>>
     */
    public static function forStep(array $step): array
    {
        $prefix = ($step['repeatable'] ?? false) ? $step['repeat_name'].'.*.' : '';

        return self::collect($step['requirements'] ?? [], $prefix);
    }
}
