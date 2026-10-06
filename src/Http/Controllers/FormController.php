<?php

declare(strict_types=1);

namespace FormStepper\FormStepper\Http\Controllers;

use FormStepper\FormStepper\Forms\FormBuilder;
use FormStepper\FormStepper\Forms\FormBuilderRegistry;
use FormStepper\FormStepper\Forms\FormResult;
use FormStepper\FormStepper\Models\Form;
use FormStepper\FormStepper\Services\FormService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class FormController
{
    public function __construct(
        private readonly FormBuilderRegistry $builders,
        private readonly FormService $forms,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $builder = $this->builders->resolve($data['type']);
        $query = $builder->scopeForms(
            Form::query()->type($builder->formType())->draft(),
            $request,
        );

        $forms = $query->with(['steps', 'selectedOptions'])
            ->latest()
            ->paginate($data['per_page'] ?? 10);
        /** @var list<Form> $items */
        $items = $forms->items();

        return response()->json([
            'data' => array_map(
                fn (Form $form): array => $this->result($form)->toArray(),
                $items,
            ),
            'meta' => [
                'current_page' => $forms->currentPage(),
                'last_page' => $forms->lastPage(),
                'per_page' => $forms->perPage(),
                'total' => $forms->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string'],
            'mode' => ['sometimes', 'in:single,stepper'],
            'options' => ['sometimes', 'array'],
            'options.*' => ['string'],
        ]);

        if (isset($data['mode']) && ! config('form-stepper.allow_instance_mode_override', false)) {
            throw ValidationException::withMessages([
                'mode' => 'Per-form mode overrides are disabled.',
            ]);
        }

        $optionKeys = $data['options'] ?? [];

        if (! array_is_list($optionKeys)) {
            throw ValidationException::withMessages([
                'options' => 'Selected option keys must be a list.',
            ]);
        }

        $builder = $this->builders->resolve($data['type']);
        $builder->authorize('create', $request);
        $result = $this->forms->create(
            $builder,
            $optionKeys,
            $builder->requester($request),
            $builder->tenant($request),
            $data['mode'] ?? null,
        );

        return response()->json($result['result']->toArray(), 201);
    }

    public function show(Request $request, string $uuid): JsonResponse
    {
        $form = $this->findForm($uuid);
        $this->authorizeForm($request, $form, 'view');

        return response()->json($this->result($form->load(['steps', 'selectedOptions']))->toArray());
    }

    public function updateOptions(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate([
            'options' => ['present', 'array'],
            'options.*' => ['string'],
        ]);
        $form = $this->findForm($uuid);
        $builder = $this->builders->resolve($form->type);
        $this->authorizeForm($request, $form, 'update', $builder);

        return response()->json(
            $this->forms->updateOptions($form, $builder, $data['options'])->toArray(),
        );
    }

    public function saveStep(Request $request, string $uuid, string $step): JsonResponse
    {
        $data = $request->validate([
            'values' => ['required', 'array'],
        ]);
        $form = $this->findForm($uuid);
        $builder = $this->builders->resolve($form->type);
        $this->authorizeForm($request, $form, 'update', $builder);

        return response()->json(
            $this->forms->saveStep($form, $step, $data['values'])->toArray(),
        );
    }

    public function submit(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate([
            'values' => ['required', 'array'],
        ]);
        $form = $this->findForm($uuid);
        $builder = $this->builders->resolve($form->type);
        $this->authorizeForm($request, $form, 'update', $builder);

        return response()->json(
            $this->forms->submitSingle($form, $data['values'])->toArray(),
        );
    }

    public function complete(Request $request, string $uuid): JsonResponse
    {
        $form = $this->findForm($uuid);
        $builder = $this->builders->resolve($form->type);
        $this->authorizeForm($request, $form, 'update', $builder);

        return response()->json($this->forms->complete($form)->toArray());
    }

    private function findForm(string $uuid): Form
    {
        return Form::query()->where('uuid', $uuid)->firstOrFail();
    }

    private function authorizeForm(
        Request $request,
        Form $form,
        string $ability,
        ?FormBuilder $builder = null,
    ): void {
        $builder ??= $this->builders->resolve($form->type);
        $user = $request->user();

        if ($user === null) {
            $builder->authorizeGuestResume($request, $form);

            return;
        }

        if ($form->requester === null && $request->hasHeader('X-Form-Resume-Token')) {
            $builder->authorizeGuestResume($request, $form);
            $requester = $builder->requester($request);

            if ($requester === null) {
                throw new InvalidArgumentException('The authenticated requester could not be resolved.');
            }

            $this->forms->claimGuest($form, $requester, $builder, $builder->tenant($request));
        }

        $builder->authorize($ability, $request, $form->refresh());
    }

    private function result(Form $form): FormResult
    {
        return new FormResult($form);
    }
}
