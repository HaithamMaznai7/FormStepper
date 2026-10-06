<?php

declare(strict_types=1);

namespace HaithamMaznai\FormStepper\Http\Controllers\Admin;

use HaithamMaznai\FormStepper\Models\FormInputType;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InputTypeController extends AdminController
{
    public function index(): View
    {
        return $this->view('form-stepper::admin.input-types.index', [
            'types' => FormInputType::query()->withCount('inputs')->orderByDesc('is_system')->orderBy('key')
                ->paginate($this->perPage()),
        ]);
    }

    public function create(): View
    {
        return $this->view('form-stepper::admin.input-types.create', ['type' => new FormInputType]);
    }

    public function store(Request $request): RedirectResponse
    {
        $type = FormInputType::create([...$this->validated($request), 'is_system' => false, 'has_children' => false]);

        return $this->redirectTo('input-types.show', $type, 'Input type created.');
    }

    public function show(FormInputType $inputType): View
    {
        return $this->view('form-stepper::admin.input-types.show', [
            'type' => $inputType,
            'inputs' => $inputType->inputs()->orderBy('key')->get(),
        ]);
    }

    public function edit(FormInputType $inputType): View
    {
        return $this->view('form-stepper::admin.input-types.edit', ['type' => $inputType]);
    }

    public function update(Request $request, FormInputType $inputType): RedirectResponse
    {
        $data = $this->validated($request, $inputType);

        if ($inputType->is_system) {
            unset($data['key']);
        }

        $inputType->update($data);

        return $this->redirectTo('input-types.show', $inputType, 'Input type updated.');
    }

    public function destroy(FormInputType $inputType): RedirectResponse
    {
        if ($inputType->is_system) {
            return back()->withErrors(['type' => 'System input types cannot be deleted.']);
        }

        if ($inputType->inputs()->exists()) {
            return back()->withErrors(['type' => 'Delete or change the inputs using this type first.']);
        }

        $inputType->delete();

        return $this->redirectTo('input-types.index', [], 'Input type deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, ?FormInputType $type = null): array
    {
        $isSystem = $type !== null && $type->is_system;
        $data = $request->validate([
            'key' => $isSystem ? ['prohibited'] : [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z0-9][a-z0-9_-]*$/',
                Rule::unique((new FormInputType)->getTable(), 'key')->ignore($type),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        return [...$data, 'has_options' => $request->boolean('has_options')];
    }
}
