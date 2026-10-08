<?php

declare(strict_types=1);

namespace HaithamMaznai\FormStepper\Support;

use HaithamMaznai\FormStepper\Models\Form;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use InvalidArgumentException;

class FormPresentation
{
    /**
     * @param  array<string, mixed>  $step
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>
     */
    public function step(Form $form, array $step, array $defaults): array
    {
        unset($step['title'], $step['subtitle']);
        $step['title'] = $this->text($form, 'steps', $step['key'], 'title', Str::headline($step['key']));
        $step['subtitle'] = $this->text($form, 'steps', $step['key'], 'subtitle');
        $step['requirements'] = $this->inputs($form, $step['requirements'] ?? [], $defaults);

        return $step;
    }

    /**
     * @param  list<array<string, mixed>>  $inputs
     * @param  array<string, mixed>  $defaults
     * @return list<array<string, mixed>>
     */
    private function inputs(Form $form, array $inputs, array $defaults): array
    {
        return array_map(function (array $input) use ($form, $defaults): array {
            $key = $input['key'];
            unset($input['label'], $input['placeholder']);
            $input['label'] = $this->text($form, 'inputs', $key, 'label', Str::headline($key));
            $input['placeholder'] = $this->text($form, 'inputs', $key, 'placeholder');

            if (Arr::has($defaults, $key)) {
                $input['value'] = Arr::get($defaults, $key);
            }

            if (($input['type'] ?? null) === 'complex') {
                $childrenDefaults = $input['value'] ?? [];
                $input['children'] = $this->inputs(
                    $form,
                    $input['children'] ?? [],
                    is_array($childrenDefaults) ? $childrenDefaults : [],
                );
            }

            return $input;
        }, $inputs);
    }

    private function text(Form $form, string $group, string $key, string $attribute, ?string $fallback = null): ?string
    {
        $segments = [$form->type, $form->mode, $form->requester_type ?? 'guest', $form->tenant_type ?? 'none'];
        $path = implode('.', array_map($this->segment(...), $segments));
        $key = $this->segment($key);
        $keys = [
            "form-stepper::forms.contexts.{$path}.{$group}.{$key}.{$attribute}",
            "form-stepper::forms.defaults.{$group}.{$key}.{$attribute}",
        ];

        foreach (array_unique([app()->getLocale(), config('app.fallback_locale', 'en')]) as $locale) {
            foreach ($keys as $translationKey) {
                if (! Lang::hasForLocale($translationKey, $locale)) {
                    continue;
                }

                $translated = __($translationKey, [], $locale);

                if (! is_string($translated)) {
                    throw new InvalidArgumentException("Form translation [{$translationKey}] must be a string.");
                }

                return $translated;
            }
        }

        return $fallback;
    }

    private function segment(string $value): string
    {
        return str_replace('.', '%2E', rawurlencode($value));
    }
}
