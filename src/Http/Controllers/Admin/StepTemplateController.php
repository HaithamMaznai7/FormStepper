<?php

declare(strict_types=1);

namespace HaithamMaznai\FormStepper\Http\Controllers\Admin;

use HaithamMaznai\FormStepper\Models\FormInput;
use HaithamMaznai\FormStepper\Models\FormStepTemplate;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StepTemplateController extends AdminController
{
    public function index(Request $request): View
    {
        $query = FormStepTemplate::query()->withCount('inputs')->orderBy('key');

        if (is_string($search = $request->query('q')) && $search !== '') {
            $query->where('key', 'like', "%{$search}%");
        }

        return $this->view('form-stepper::admin.steps.index', [
            'steps' => $query->paginate($this->perPage())->withQueryString(),
        ]);
    }

    public function create(): View
    {
        return $this->formView('form-stepper::admin.steps.create', new FormStepTemplate);
    }

    public function store(Request $request): RedirectResponse
    {
        $step = $this->persist($request, new FormStepTemplate);

        return $this->redirectTo('steps.show', $step, 'Step created.');
    }

    public function show(FormStepTemplate $step): View
    {
        $step->load('inputs.children');

        return $this->view('form-stepper::admin.steps.show', [
            'step' => $step,
            'snapshot' => $this->assertSchema(fn () => $step->toStep()->toArray()),
        ]);
    }

    public function edit(FormStepTemplate $step): View
    {
        return $this->formView('form-stepper::admin.steps.edit', $step->load('inputs'));
    }

    public function update(Request $request, FormStepTemplate $step): RedirectResponse
    {
        $this->persist($request, $step);

        return $this->redirectTo('steps.show', $step, 'Step updated. Existing option snapshots keep their copy.');
    }

    public function destroy(FormStepTemplate $step): RedirectResponse
    {
        $step->delete();

        return $this->redirectTo('steps.index', [], 'Step deleted.');
    }

    /**
     * @param  view-string  $view
     */
    protected function formView(string $view, FormStepTemplate $step): View
    {
        return $this->view($view, [
            'step' => $step,
            'inputs' => FormInput::query()->orderBy('key')->get(),
            'positions' => $step->exists
                ? $step->inputs->mapWithKeys(fn (FormInput $input): array => [
                    $input->getKey() => (int) $input->getRelationValue('pivot')?->getAttribute('position') + 1,
                ])->all()
                : [],
        ]);
    }

    protected function persist(Request $request, FormStepTemplate $step): FormStepTemplate
    {
        $inputsTable = (new FormInput)->getTable();
        $data = $request->validate([
            'key' => [
                'required',
                'string',
                'max:150',
                'regex:/^[A-Za-z0-9_][A-Za-z0-9_.-]*$/',
                Rule::notIn(['review']),
                Rule::unique($step->getTable(), 'key')->ignore($step->exists ? $step : null),
            ],
            'priority' => ['nullable', 'integer', 'between:-2147483648,2147483647'],
            'repeat_name' => ['nullable', 'required_if_accepted:repeatable', 'string', 'max:150', 'regex:/^[A-Za-z0-9_]+$/'],
            'inputs' => ['required', 'array', 'min:1'],
            'inputs.*' => ['integer', Rule::exists($inputsTable, 'id')],
            'positions' => ['nullable', 'array'],
            'positions.*' => ['nullable', 'integer', 'min:0'],
        ]);

        $positions = $data['positions'] ?? [];
        $inputIds = array_values(collect(array_unique(array_map(intval(...), $data['inputs'])))
            ->sortBy(static fn (int $id): int => (int) ($positions[$id] ?? PHP_INT_MAX))
            ->all());

        $repeatable = $request->boolean('repeatable');
        $attributes = [
            'key' => $data['key'],
            'priority' => (int) ($data['priority'] ?? 100),
            'repeatable' => $repeatable,
            'repeat_name' => $repeatable ? ($data['repeat_name'] ?? null) : null,
            'requires_authentication' => $request->boolean('requires_authentication'),
        ];

        return DB::transaction(function () use ($step, $attributes, $inputIds): FormStepTemplate {
            $step->fill($attributes)->save();
            $step->syncInputs($inputIds);
            $step->unsetRelation('inputs');
            $this->assertSchema(fn () => $step->toStep(), 'inputs');

            return $step;
        });
    }
}
