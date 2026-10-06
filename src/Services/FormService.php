<?php

declare(strict_types=1);

namespace HaithamMaznai\FormStepper\Services;

use HaithamMaznai\FormStepper\Contracts\ProvidesFormRequirements;
use HaithamMaznai\FormStepper\Forms\FormBuilder;
use HaithamMaznai\FormStepper\Forms\FormDefinition;
use HaithamMaznai\FormStepper\Forms\FormResult;
use HaithamMaznai\FormStepper\Models\Form;
use HaithamMaznai\FormStepper\Models\FormOption;
use HaithamMaznai\FormStepper\Models\FormStep;
use HaithamMaznai\FormStepper\Support\FormOwnership;
use HaithamMaznai\FormStepper\Support\RuleCollector;
use HaithamMaznai\FormStepper\Support\SchemaAssembler;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class FormService
{
    public function __construct(
        private readonly SchemaAssembler $assembler,
    ) {}

    /**
     * @param  list<string>  $optionKeys
     * @return array{result: FormResult, form: Form, resume_token: ?string}
     */
    public function create(
        FormBuilder $builder,
        array $optionKeys,
        ?Model $requester,
        ?Model $tenant,
        ?string $requestedMode = null,
    ): array {
        FormOwnership::validate($requester, $tenant);
        $options = $this->resolveOptions($builder, $optionKeys);
        $guest = $requester === null;
        $mode = $builder->resolveMode($requestedMode);
        $definition = $this->definition($builder, $options, $requester, $tenant, $guest, $mode);
        $token = $guest ? bin2hex(random_bytes(32)) : null;
        $firstStep = $definition->mode === 'stepper'
            ? ($definition->steps[0]['key'] ?? 'review')
            : null;

        $form = DB::transaction(function () use (

            $definition,
            $options,
            $requester,
            $tenant,
            $builder,
            $token,
            $firstStep,
        ): Form {
            $attributes = [
                'uuid' => (string) Str::uuid(),
                'type' => $definition->type,
                'mode' => $definition->mode,
                'requester_type' => $requester?->getMorphClass(),
                'requester_id' => $requester?->getKey(),
                'status' => 'draft',
                'current_step_id' => $firstStep,
                'definition' => $definition->toArray(),
                'resume_token_hash' => $token === null ? null : password_hash($token, PASSWORD_DEFAULT),
            ];

            if (FormOwnership::tenantEnabled()) {
                $attributes['tenant_type'] = $tenant?->getMorphClass();
                $attributes['tenant_id'] = $tenant?->getKey();
            }

            $form = Form::create($attributes);

            foreach ($options as $option) {
                FormOption::create([
                    'form_id' => $form->getKey(),
                    'option_type' => $option->getMorphClass(),
                    'option_id' => $option->getKey(),
                    'option_key' => $option->formOptionKey(),
                ]);
            }

            $this->storePrefillValues($form, $builder, $definition, $requester);

            return $form;
        });

        return [
            'result' => new FormResult($form->load(['steps', 'selectedOptions']), $token),
            'form' => $form,
            'resume_token' => $token,
        ];
    }

    /**
     * @param  list<string>  $optionKeys
     */
    public function updateOptions(
        Form $form,
        FormBuilder $builder,
        array $optionKeys,
    ): FormResult {
        $options = $this->resolveOptions($builder, $optionKeys);

        $updatedForm = DB::transaction(function () use ($form, $builder, $options): Form {
            $form = Form::query()->lockForUpdate()->whereKey($form->getKey())->firstOrFail();
            $this->assertDraft($form);
            $previousDefinition = $form->definition ?? [];
            $definition = $this->definition(
                $builder,
                $options,
                $form->requester,
                $form->tenant,
                $form->requester === null,
                $form->mode,
            );
            $form->selectedOptions()->delete();

            foreach ($options as $option) {
                FormOption::create([
                    'form_id' => $form->getKey(),
                    'option_type' => $option->getMorphClass(),
                    'option_id' => $option->getKey(),
                    'option_key' => $option->formOptionKey(),
                ]);
            }

            $form->definition = $definition->toArray();
            $this->retainCompatibleValues($form, $previousDefinition['steps'] ?? [], $definition->steps);
            $form->unsetRelation('steps');
            $form->current_step_id = $form->mode === 'stepper'
                ? $this->nextRequiredStep($form, $definition->steps)
                : null;
            $form->save();

            return $form;
        });

        return new FormResult($updatedForm->load(['steps', 'selectedOptions']));
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function saveStep(Form $form, string $stepKey, array $values): FormResult
    {
        $updatedForm = DB::transaction(function () use ($form, $stepKey, $values): Form {
            $form = Form::query()->lockForUpdate()->whereKey($form->getKey())->firstOrFail();
            $this->assertDraft($form);

            if ($form->mode !== 'stepper' || $form->current_step_id !== $stepKey) {
                throw new ConflictHttpException('Only the current step of a stepper form can be saved.');
            }

            $step = $this->findStep($form, $stepKey);

            if ($step === null || $stepKey === 'review') {
                throw ValidationException::withMessages([
                    'step' => "The step [{$stepKey}] does not exist.",
                ]);
            }

            if ($step['requires_authentication'] && $form->requester === null) {
                throw new AuthorizationException('Authentication is required before this step can be saved.');
            }

            $values = $this->validateStep($step, $values);

            FormStep::updateOrCreate(
                ['form_id' => $form->getKey(), 'step_key' => $stepKey],
                ['values' => $values, 'saved_at' => now()],
            );

            $stepKeys = array_column($form->definition['steps'] ?? [], 'key');
            $position = array_search($stepKey, $stepKeys, true);
            $form->current_step_id = $stepKeys[$position + 1] ?? 'review';
            $form->save();

            return $form;
        });

        return new FormResult($updatedForm->load(['steps', 'selectedOptions']));
    }

    /**
     * Replace the saved values of any step of a draft (admin editing). Unlike `saveStep`, this
     * does not require the step to be current and does not advance past later steps.
     *
     * @param  array<string, mixed>  $values
     */
    public function updateStepValues(Form $form, string $stepKey, array $values): FormResult
    {
        $updatedForm = DB::transaction(function () use ($form, $stepKey, $values): Form {
            $form = Form::query()->lockForUpdate()->whereKey($form->getKey())->firstOrFail();
            $this->assertDraft($form);
            $step = $this->findStep($form, $stepKey);

            if ($step === null) {
                throw ValidationException::withMessages([
                    'step' => "The step [{$stepKey}] does not exist.",
                ]);
            }

            FormStep::updateOrCreate(
                ['form_id' => $form->getKey(), 'step_key' => $stepKey],
                ['values' => $this->validateStep($step, $values), 'saved_at' => now()],
            );

            if ($form->mode === 'stepper') {
                $form->current_step_id = $this->nextRequiredStep($form, $form->definition['steps'] ?? []);
                $form->save();
            }

            return $form;
        });

        return new FormResult($updatedForm->load(['steps', 'selectedOptions']));
    }

    /**
     * @param  array<string, mixed>  $values  Values grouped by step key.
     */
    public function submitSingle(Form $form, array $values): FormResult
    {
        $updatedForm = DB::transaction(function () use ($form, $values): Form {
            $form = Form::query()->lockForUpdate()->whereKey($form->getKey())->firstOrFail();
            $this->assertDraft($form);

            if ($form->mode !== 'single') {
                throw new ConflictHttpException('Only a single-mode form can be submitted this way.');
            }

            if (
                $form->requester === null &&
                ($form->definition['has_authentication_required_steps'] ?? false)
            ) {
                throw new AuthorizationException('Authentication is required to submit this form.');
            }

            $steps = $form->definition['steps'] ?? [];

            foreach ($steps as $step) {
                if ($step['requires_authentication'] && $form->requester === null) {
                    throw new AuthorizationException('Authentication is required to submit this form.');
                }

                $stepValues = $values[$step['key']] ?? [];

                if (! is_array($stepValues)) {
                    throw new InvalidArgumentException("Values for step [{$step['key']}] must be an object.");
                }

                $values[$step['key']] = $this->validateStep($step, $stepValues);
            }

            $unknownSteps = array_diff(array_keys($values), array_column($steps, 'key'));

            if ($unknownSteps !== []) {
                throw ValidationException::withMessages([
                    'values' => 'Values were submitted for unknown form steps.',
                ]);
            }

            foreach ($steps as $step) {
                FormStep::updateOrCreate(
                    ['form_id' => $form->getKey(), 'step_key' => $step['key']],
                    ['values' => $values[$step['key']] ?? [], 'saved_at' => now()],
                );
            }

            $form->status = 'submitted';
            $form->completed_at = now();
            $form->save();

            return $form;
        });

        return new FormResult($updatedForm->load(['steps', 'selectedOptions']));
    }

    public function complete(Form $form): FormResult
    {
        $updatedForm = DB::transaction(function () use ($form): Form {
            $form = Form::query()->lockForUpdate()->whereKey($form->getKey())->firstOrFail();
            $this->assertDraft($form);

            if ($form->mode !== 'stepper' || $form->current_step_id !== 'review') {
                throw new ConflictHttpException('A stepper form can only be completed from its review step.');
            }

            if (
                $form->requester === null &&
                ($form->definition['has_authentication_required_steps'] ?? false)
            ) {
                throw new AuthorizationException('Authentication is required to complete this form.');
            }

            foreach ($form->definition['steps'] ?? [] as $step) {
                if ($step['requires_authentication'] && $form->requester === null) {
                    throw new AuthorizationException('Authentication is required to complete this form.');
                }

                $savedStep = $form->steps()->where('step_key', $step['key'])->first();

                if ($savedStep === null) {
                    throw new ConflictHttpException("Step [{$step['key']}] has not been saved.");
                }

                $this->validateStep($step, $savedStep->values ?? []);
            }

            $form->status = 'submitted';
            $form->completed_at = now();
            $form->save();

            return $form;
        });

        return new FormResult($updatedForm->load(['steps', 'selectedOptions']));
    }

    public function claimGuest(Form $form, Model $requester, FormBuilder $builder, ?Model $tenant = null): void
    {
        FormOwnership::validate($requester, $tenant);
        DB::transaction(function () use ($form, $requester, $builder, $tenant): void {
            $form = Form::query()->lockForUpdate()->whereKey($form->getKey())->firstOrFail();

            if (
                $form->getAttribute('requester_type') !== null ||
                $form->getAttribute('requester_id') !== null ||
                $form->getAttribute('tenant_type') !== null ||
                $form->getAttribute('tenant_id') !== null
            ) {
                throw new ConflictHttpException('Only a guest form can be claimed.');
            }

            $previousSteps = $form->definition['steps'] ?? [];
            $form->requester()->associate($requester);

            if (FormOwnership::tenantEnabled()) {
                $form->tenant()->associate($tenant);
            }
            $form->resume_token_hash = null;
            $form->save();

            $optionKeys = [];

            foreach ($form->selectedOptions as $selectedOption) {
                $optionKeys[] = $selectedOption->option_key;
            }

            $options = $this->resolveOptions($builder, $optionKeys);
            $definition = $this->definition(
                $builder,
                $options,
                $requester,
                $form->tenant,
                false,
                $form->mode,
            );
            $form->definition = $definition->toArray();
            $this->retainCompatibleValues($form, $previousSteps, $definition->steps);
            $form->unsetRelation('steps');
            $form->current_step_id = $form->mode === 'stepper'
                ? $this->nextRequiredStep($form, $definition->steps)
                : null;
            $form->save();

            $prefill = $builder->prefillValues($requester);

            foreach ($prefill as $stepKey => $values) {
                $step = $this->findStep($form, $stepKey);

                if ($step === null) {
                    throw new InvalidArgumentException(
                        "Prefill values reference unknown step [{$stepKey}].",
                    );
                }

                $values = $this->filterStepValues($step, $values);
                $savedStep = $form->steps()->where('step_key', $stepKey)->first();

                if ($savedStep === null) {
                    FormStep::create([
                        'form_id' => $form->getKey(),
                        'step_key' => $stepKey,
                        'values' => $values,
                        'saved_at' => now(),
                    ]);

                    continue;
                }

                $savedValues = $savedStep->values ?? [];

                if ($step['repeatable']) {
                    if (! array_key_exists($step['repeat_name'], $savedValues)) {
                        $savedValues[$step['repeat_name']] = $values[$step['repeat_name']] ?? [];
                    }
                } else {
                    $savedValues = $this->mergeMissingValues(
                        $savedValues,
                        $values,
                        $step['requirements'],
                    );
                }

                $savedStep->values = $savedValues;
                $savedStep->save();
            }
        });
    }

    /**
     * @param  list<string>  $optionKeys
     * @return list<ProvidesFormRequirements&Model>
     */
    private function resolveOptions(FormBuilder $builder, array $optionKeys): array
    {
        if (count($optionKeys) !== count(array_unique($optionKeys))) {
            throw ValidationException::withMessages([
                'options' => 'Selected option keys must be unique.',
            ]);
        }

        $options = $builder->resolveOptions($optionKeys);
        $resolvedKeys = [];

        foreach ($options as $option) {
            if (! $option instanceof Model || ! $option instanceof ProvidesFormRequirements) {
                throw new InvalidArgumentException(
                    'Every selected option must be an Eloquent model implementing ProvidesFormRequirements.',
                );
            }

            $resolvedKeys[] = $option->formOptionKey();
        }

        sort($optionKeys);
        sort($resolvedKeys);

        if ($optionKeys !== $resolvedKeys) {
            throw new InvalidArgumentException('The form builder did not resolve exactly the selected options.');
        }

        return $options;
    }

    /**
     * @param  list<ProvidesFormRequirements&Model>  $options
     */
    private function definition(
        FormBuilder $builder,
        array $options,
        ?Model $requester,
        ?Model $tenant,
        bool $guest,
        ?string $mode = null,
    ): FormDefinition {
        return $this->assembler->assemble($builder, $options, $requester, $tenant, $guest, $mode);
    }

    /**
     * @param  array<string, mixed>  $step
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function validateStep(array $step, array $values): array
    {
        $values = $this->filterStepValues($step, $values);

        Validator::make($values, RuleCollector::forStep($step))->validate();

        return $values;
    }

    /**
     * @param  array<string, mixed>  $step
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function filterStepValues(array $step, array $values): array
    {
        if (! $step['repeatable']) {
            return $this->filterRequirementValues($values, $step['requirements']);
        }

        $repeatName = $step['repeat_name'] ?? null;

        if ($repeatName === null) {
            throw new InvalidArgumentException(
                "Repeatable step [{$step['key']}] must define a repeat-name.",
            );
        }

        $instances = $values[$repeatName] ?? [];

        if (! is_array($instances) || ! array_is_list($instances)) {
            throw ValidationException::withMessages([
                $repeatName => 'Repeated step values must be a list.',
            ]);
        }

        if ($instances === [] && $this->hasRequiredRule($step['requirements'])) {
            throw ValidationException::withMessages([
                $repeatName => 'At least one repeated value is required for this step.',
            ]);
        }

        if (! $step['can_repeat'] && count($instances) > 1) {
            throw ValidationException::withMessages([
                $repeatName => "Step [{$step['key']}] does not allow multiple repeated instances in this context.",
            ]);
        }

        $values = $this->onlyAllowedValues($values, [$repeatName]);
        $values[$repeatName] = array_map(
            fn (mixed $instance): array => $this->filterRequirementValues(
                $instance,
                $step['requirements'],
            ),
            $instances,
        );

        return $values;
    }

    /**
     * @param  list<array<string, mixed>>  $newSteps
     * @param  list<array<string, mixed>>  $previousSteps
     */
    private function retainCompatibleValues(Form $form, array $previousSteps, array $newSteps): void
    {
        $previousDefinitions = [];
        $definitions = [];

        foreach ($previousSteps as $step) {
            $previousDefinitions[$step['key']] = $step;
        }

        foreach ($newSteps as $step) {
            $definitions[$step['key']] = $step;
        }

        foreach ($form->steps as $savedStep) {
            $definition = $definitions[$savedStep->step_key] ?? null;
            $previousDefinition = $previousDefinitions[$savedStep->step_key] ?? null;

            if ($definition === null || $previousDefinition === null) {
                $savedStep->delete();

                continue;
            }

            $values = $savedStep->values ?? [];

            if (
                $definition['repeatable'] &&
                $previousDefinition['repeatable'] &&
                $definition['repeat_name'] === $previousDefinition['repeat_name']
            ) {
                $instances = $values[$definition['repeat_name']] ?? [];
                $values[$definition['repeat_name']] = array_map(
                    fn (array $instance): array => $this->retainRequirements(
                        $instance,
                        $previousDefinition['requirements'],
                        $definition['requirements'],
                    ),
                    is_array($instances) ? $instances : [],
                );
            } elseif (! $definition['repeatable'] && ! $previousDefinition['repeatable']) {
                $values = $this->retainRequirements(
                    $values,
                    $previousDefinition['requirements'],
                    $definition['requirements'],
                );
            } else {
                $values = [];
            }

            $savedStep->values = $values;
            $savedStep->save();
        }
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  list<array<string, mixed>>  $previousRequirements
     * @param  list<array<string, mixed>>  $requirements
     * @return array<string, mixed>
     */
    private function retainRequirements(array $values, array $previousRequirements, array $requirements): array
    {
        $retained = [];

        foreach ($requirements as $requirement) {
            $previous = $this->findRequirement($previousRequirements, $requirement['key']);

            if ($previous === null || $previous['type'] !== $requirement['type']) {
                continue;
            }

            if (! Arr::has($values, $requirement['key'])) {
                continue;
            }

            $value = Arr::get($values, $requirement['key']);

            if ($requirement['type'] === 'complex' && is_array($value)) {
                $value = $this->retainRequirements(
                    $value,
                    $previous['children'],
                    $requirement['children'],
                );
            }

            Arr::set($retained, $requirement['key'], $value);
        }

        return $retained;
    }

    /**
     * @param  array<string, mixed>  $existing
     * @param  array<string, mixed>  $defaults
     * @param  list<array<string, mixed>>  $requirements
     * @return array<string, mixed>
     */
    private function mergeMissingValues(array $existing, array $defaults, array $requirements): array
    {
        foreach ($requirements as $requirement) {
            if (Arr::has($existing, $requirement['key'])) {
                if ($requirement['type'] === 'complex') {
                    $existingValue = Arr::get($existing, $requirement['key']);
                    $defaultValue = Arr::get($defaults, $requirement['key']);

                    if (is_array($existingValue) && is_array($defaultValue)) {
                        Arr::set(
                            $existing,
                            $requirement['key'],
                            $this->mergeMissingValues(
                                $existingValue,
                                $defaultValue,
                                $requirement['children'],
                            ),
                        );
                    }
                }

                continue;
            }

            if (Arr::has($defaults, $requirement['key'])) {
                Arr::set($existing, $requirement['key'], Arr::get($defaults, $requirement['key']));
            }
        }

        return $existing;
    }

    /**
     * @param  list<array<string, mixed>>  $steps
     */
    private function nextRequiredStep(Form $form, array $steps): string
    {
        foreach ($steps as $step) {
            $savedStep = $form->steps()->where('step_key', $step['key'])->first();

            if ($savedStep === null) {
                return $step['key'];
            }

            try {
                $this->validateStep($step, $savedStep->values ?? []);
            } catch (ValidationException) {
                return $step['key'];
            }
        }

        return 'review';
    }

    /**
     * @param  list<array<string, mixed>>  $requirements
     */
    private function hasRequiredRule(array $requirements): bool
    {
        foreach ($requirements as $requirement) {
            foreach ($requirement['rules'] as $rule) {
                if (is_string($rule) && preg_match('/^required(?:_|$)/', $rule) === 1) {
                    return true;
                }
            }

            if (
                $requirement['type'] === 'complex' &&
                $this->hasRequiredRule($requirement['children'])
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  list<string>  $allowedKeys
     * @return array<string, mixed>
     */
    private function onlyAllowedValues(array $values, array $allowedKeys): array
    {
        $unexpectedKeys = array_diff(array_keys($values), $allowedKeys);

        if ($unexpectedKeys !== [] && config('form-stepper.throw_on_extra_values', true)) {
            throw ValidationException::withMessages([
                'values' => 'Values include fields that are not part of the current form schema.',
            ]);
        }

        return array_intersect_key($values, array_flip($allowedKeys));
    }

    /**
     * @param  list<array<string, mixed>>  $requirements
     * @return array<string, mixed>
     */
    private function filterRequirementValues(mixed $values, array $requirements): array
    {
        if (! is_array($values)) {
            throw ValidationException::withMessages([
                'values' => 'Step values must be an object.',
            ]);
        }

        $retained = [];
        $allowedRoots = array_values(array_unique(array_map(
            static fn (array $requirement): string => explode('.', $requirement['key'])[0],
            $requirements,
        )));

        $values = $this->onlyAllowedValues($values, $allowedRoots);

        foreach ($requirements as $requirement) {
            if (! Arr::has($values, $requirement['key'])) {
                continue;
            }

            $value = Arr::get($values, $requirement['key']);

            if ($requirement['type'] === 'complex' && is_array($value)) {
                $value = $this->filterRequirementValues($value, $requirement['children']);
            }

            Arr::set($retained, $requirement['key'], $value);
        }

        return $retained;
    }

    /**
     * @param  list<array<string, mixed>>  $requirements
     * @return array<string, mixed>|null
     */
    private function findRequirement(array $requirements, string $key): ?array
    {
        foreach ($requirements as $requirement) {
            if ($requirement['key'] === $key) {
                return $requirement;
            }
        }

        return null;
    }

    private function storePrefillValues(
        Form $form,
        FormBuilder $builder,
        FormDefinition $definition,
        ?Model $requester,
    ): void {
        $prefill = $builder->prefillValues($requester);
        $steps = [];

        foreach ($definition->steps as $step) {
            $steps[$step['key']] = $step;
        }

        foreach ($prefill as $stepKey => $values) {
            $step = $steps[$stepKey] ?? null;

            if ($step === null) {
                throw new InvalidArgumentException(
                    "Prefill values reference unknown step [{$stepKey}].",
                );
            }

            FormStep::create([
                'form_id' => $form->getKey(),
                'step_key' => $stepKey,
                'values' => $this->filterStepValues($step, $values),
                'saved_at' => now(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findStep(Form $form, string $key): ?array
    {
        foreach ($form->definition['steps'] ?? [] as $step) {
            if ($step['key'] === $key) {
                return $step;
            }
        }

        return null;
    }

    private function assertDraft(Form $form): void
    {
        if ($form->status !== 'draft') {
            throw new ConflictHttpException('Completed forms cannot be changed.');
        }
    }
}
