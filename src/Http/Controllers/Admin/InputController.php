<?php

declare(strict_types=1);

namespace HaithamMaznai\FormStepper\Http\Controllers\Admin;

use HaithamMaznai\FormStepper\Models\FormInput;
use HaithamMaznai\FormStepper\Models\FormInputType;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InputController extends AdminController
{
    public function index(Request $request): View
    {
        $query = FormInput::query()->withCount(['stepTemplates', 'parents'])->orderBy('key');

        if (is_string($search = $request->query('q')) && $search !== '') {
            $query->where('key', 'like', "%{$search}%");
        }

        if (is_string($type = $request->query('type')) && $type !== '') {
            $query->where('type', $type);
        }

        return $this->view('form-stepper::admin.inputs.index', [
            'inputs' => $query->paginate($this->perPage())->withQueryString(),
            'types' => FormInputType::query()->orderBy('key')->get(),
        ]);
    }

    public function create(): View
    {
        return $this->formView('form-stepper::admin.inputs.create', new FormInput(['rules' => []]));
    }

    public function store(Request $request): RedirectResponse
    {
        $input = $this->persist($request, new FormInput);

        return $this->redirectTo('inputs.show', $input, 'Input created.');
    }

    public function show(FormInput $input): View
    {
        $input->load(['children', 'parents', 'stepTemplates', 'inputType']);

        return $this->view('form-stepper::admin.inputs.show', [
            'input' => $input,
            'snapshot' => $this->assertSchema(fn () => $input->toInput()->toArray()),
        ]);
    }

    public function edit(FormInput $input): View
    {
        return $this->formView('form-stepper::admin.inputs.edit', $input->load('children'));
    }

    public function update(Request $request, FormInput $input): RedirectResponse
    {
        $this->persist($request, $input);

        return $this->redirectTo('inputs.show', $input, 'Input updated. Existing option snapshots keep their copy.');
    }

    public function destroy(FormInput $input): RedirectResponse
    {
        $input->delete();

        return $this->redirectTo('inputs.index', [], 'Input deleted.');
    }

    /**
     * @param  view-string  $view
     */
    protected function formView(string $view, FormInput $input): View
    {
        return $this->view($view, [
            'input' => $input,
            'types' => FormInputType::query()->orderByDesc('is_system')->orderBy('key')->get(),
            'candidates' => FormInput::query()->when($input->exists, fn (Builder $query): Builder => $query->whereKeyNot($input->getKey()))
                ->orderBy('key')->get(),
        ]);
    }

    protected function persist(Request $request, FormInput $input): FormInput
    {
        $data = $request->validate([
            'key' => [
                'required',
                'string',
                'max:150',
                'regex:/^[A-Za-z0-9_][A-Za-z0-9_.-]*$/',
                Rule::unique($input->getTable(), 'key')->ignore($input->exists ? $input : null),
            ],
            'type' => ['required', 'string', Rule::exists((new FormInputType)->getTable(), 'key')],
            'rules' => ['nullable', 'string', 'max:5000'],
            'default_value' => ['nullable', 'string', 'max:5000'],
            'options' => ['nullable', 'string', 'max:20000'],
            'extra' => ['nullable', 'string', 'max:20000'],
            'children' => ['nullable', 'array'],
            'children.*' => ['integer', Rule::exists($input->getTable(), 'id')],
        ]);

        $children = array_values(array_unique(array_map('intval', $data['children'] ?? [])));
        $isComplex = $data['type'] === 'complex';
        $extra = $this->json($data['extra'] ?? null, 'extra');

        if ($extra !== null && (! is_array($extra) || array_is_list($extra) && $extra !== [])) {
            throw ValidationException::withMessages(['extra' => 'Extra must be a JSON object.']);
        }

        $attributes = [
            'key' => $data['key'],
            'type' => $data['type'],
            'rules' => $this->rules($data['rules'] ?? null),
            'default_value' => $this->defaultValue($data['default_value'] ?? null),
            'options' => $this->options($data['options'] ?? null),
            'extra' => $extra,
        ];

        return DB::transaction(function () use ($input, $attributes, $children, $isComplex): FormInput {
            $input->fill($attributes)->save();
            $this->assertSchema(fn () => $input->syncChildren($isComplex ? $children : []), 'children');
            $input->unsetRelation('children');
            $this->assertSchema(fn () => $input->toInput(), 'type');

            return $input;
        });
    }

    /**
     * Accept JSON for structured defaults and fall back to the raw string.
     */
    protected function defaultValue(?string $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }

    /**
     * One rule per line; `required|string` lines are split, except `regex:` rules.
     *
     * @return list<string>
     */
    protected function rules(?string $value): array
    {
        $rules = [];

        foreach (preg_split("/\r\n|\r|\n/", (string) $value) ?: [] as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $parts = str_starts_with($line, 'regex:') || str_starts_with($line, 'not_regex:')
                ? [$line]
                : explode('|', $line);

            foreach ($parts as $rule) {
                if (trim($rule) !== '') {
                    $rules[] = trim($rule);
                }
            }
        }

        return $rules;
    }

    /**
     * Parse `value|label` lines into choice options; a line without `|` uses the value as label.
     *
     * @return list<array{value: string, label: string}>|null
     */
    protected function options(?string $value): ?array
    {
        $options = [];

        foreach (preg_split("/\r\n|\r|\n/", (string) $value) ?: [] as $line) {
            if (trim($line) === '') {
                continue;
            }

            $parts = explode('|', $line, 2);
            $optionValue = trim($parts[0]);
            $options[] = ['value' => $optionValue, 'label' => trim($parts[1] ?? $optionValue)];
        }

        return $options === [] ? null : $options;
    }
}
