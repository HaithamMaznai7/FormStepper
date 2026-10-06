<?php

declare(strict_types=1);

namespace HaithamMaznai\FormStepper\Http\Controllers\Admin;

use HaithamMaznai\FormStepper\Forms\FormBuilderRegistry;
use HaithamMaznai\FormStepper\Models\Form;
use HaithamMaznai\FormStepper\Services\FormService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use LogicException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class FormController extends AdminController
{
    public function __construct(
        protected readonly FormService $forms,
        protected readonly FormBuilderRegistry $builders,
    ) {}

    public function index(Request $request): View
    {
        $query = Form::query()->withCount(['steps', 'selectedOptions'])->latest('id');

        foreach (['type', 'status', 'mode'] as $filter) {
            if (is_string($value = $request->query($filter)) && $value !== '') {
                $query->where($filter, $value);
            }
        }

        return $this->view('form-stepper::admin.forms.index', [
            'forms' => $query->paginate($this->perPage())->withQueryString(),
            'types' => $this->builders->types(),
        ]);
    }

    public function create(): View
    {
        return $this->view('form-stepper::admin.forms.create', [
            'types' => $this->builders->types(),
            'allowModeOverride' => (bool) config('form-stepper.allow_instance_mode_override', false),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string', Rule::in($this->builders->types())],
            'options' => ['nullable', 'string', 'max:5000'],
            'mode' => ['nullable', Rule::in(['single', 'stepper'])],
        ]);

        $builder = $this->builders->resolve($data['type']);

        $created = $this->attempt(fn (): array => $this->forms->create(
            $builder,
            $this->optionKeys($data['options'] ?? null),
            $builder->requester($request),
            $builder->tenant($request),
            $data['mode'] ?? null,
        ), 'options');

        return $this->redirectTo('forms.edit', $created['form'], 'Draft form created.');
    }

    public function show(Form $form): View
    {
        $form->load(['steps', 'selectedOptions']);

        return $this->view('form-stepper::admin.forms.show', [
            'form' => $form,
            'values' => $form->valuesByStep(),
            'steps' => $form->definition['steps'] ?? [],
        ]);
    }

    public function edit(Form $form): View|RedirectResponse
    {
        if ($form->status !== 'draft') {
            return $this->redirectTo('forms.show', $form)->withErrors(['form' => 'Only draft forms can be edited.']);
        }

        $form->load(['steps', 'selectedOptions']);

        return $this->view('form-stepper::admin.forms.edit', [
            'form' => $form,
            'values' => $form->valuesByStep(),
            'steps' => $form->definition['steps'] ?? [],
            'optionKeys' => $form->selectedOptions->pluck('option_key')->implode(', '),
        ]);
    }

    public function update(Request $request, Form $form): RedirectResponse
    {
        $data = $request->validate(['options' => ['nullable', 'string', 'max:5000']]);
        $builder = $this->builders->resolve($form->type);

        $this->attempt(
            fn () => $this->forms->updateOptions($form, $builder, $this->optionKeys($data['options'] ?? null)),
            'options',
        );

        return $this->redirectTo('forms.edit', $form, 'Form options updated.');
    }

    public function updateStep(Request $request, Form $form, string $stepKey): RedirectResponse
    {
        $values = $request->input('values', []);
        $values = is_array($values) ? $values : [];

        foreach ((array) $request->input('json_values', []) as $key => $json) {
            $values[$key] = $this->json(is_string($json) ? $json : null, "json_values.{$key}");
        }

        if ($request->has('step_json')) {
            $decoded = $this->json((string) $request->input('step_json'), 'step_json');
            $values = is_array($decoded) ? $decoded : [];
        }

        $this->attempt(fn () => $this->forms->updateStepValues($form, $stepKey, $values), 'step');

        return $this->redirectTo('forms.edit', $form, "Step [{$stepKey}] saved.");
    }

    public function destroy(Form $form): RedirectResponse
    {
        $form->delete();

        return $this->redirectTo('forms.index', [], 'Form deleted.');
    }

    /**
     * @return list<string>
     */
    protected function optionKeys(?string $value): array
    {
        return array_values(array_unique(array_filter(
            array_map(trim(...), preg_split('/[\s,]+/', (string) $value) ?: []),
            static fn (string $key): bool => $key !== '',
        )));
    }

    /**
     * Report service conflicts and schema errors on a form field.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    protected function attempt(callable $callback, string $field): mixed
    {
        try {
            return $callback();
        } catch (InvalidArgumentException|LogicException $exception) {
            throw ValidationException::withMessages([$field => $exception->getMessage()]);
        } catch (HttpExceptionInterface $exception) {
            if ($exception->getStatusCode() >= 500 || $exception->getStatusCode() === 403) {
                throw $exception;
            }

            throw ValidationException::withMessages([$field => $exception->getMessage()]);
        }
    }
}
