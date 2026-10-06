<?php

declare(strict_types=1);

use FormStepper\FormStepper\Contracts\ProvidesFormRequirements;
use FormStepper\FormStepper\Forms\FormBuilder;
use FormStepper\FormStepper\Forms\FormBuilderRegistry;
use FormStepper\FormStepper\Models\Form;
use FormStepper\FormStepper\Services\FormService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $migration = require __DIR__.'/../../database/migrations/2026_01_01_000000_create_forms_table.php';
    $migration->up();
});

it('uses the configured forms table in the preserved form items migration', function () {
    config()->set('form-stepper.tables.forms', 'requests');
    config()->set('form-stepper.tables.options', 'saleables');
    config()->set('form-stepper.tables.steps', 'request_steps');

    $formsMigration = require __DIR__.'/../../database/migrations/2026_01_01_000000_create_forms_table.php';
    $formsMigration->up();

    expect(Schema::hasTable('saleables'))->toBeTrue()
        ->and(Schema::getForeignKeys('saleables')[0]['foreign_table'])->toBe('requests');

    $formsMigration->down();

    expect(Schema::hasTable('saleables'))->toBeFalse()
        ->and(Schema::hasTable('requests'))->toBeFalse();
});

it('persists step values and resumes a draft after loading it again', function () {
    $builder = new class extends FormBuilder
    {
        public function formType(): string
        {
            return 'application';
        }

        public function mode(): string
        {
            return 'stepper';
        }

        public function steps(): array
        {
            return [[
                'key' => 'applicant',
                'requirements' => [
                    ['key' => 'name', 'type' => 'input', 'rules' => ['required', 'string']],
                ],
            ]];
        }
    };

    $service = app(FormService::class);
    $created = $service->create($builder, [], null, null);

    expect($created['form']->current_step_id)->toBe('applicant')
        ->and($created['resume_token'])->toBeString();

    $service->saveStep($created['form'], 'applicant', ['name' => 'Ada']);

    $resumed = Form::query()->where('uuid', $created['form']->uuid)->firstOrFail();

    expect($resumed->current_step_id)->toBe('review')
        ->and($resumed->steps()->firstOrFail()->values)->toBe(['name' => 'Ada']);

    $service->complete($resumed);

    expect($resumed->refresh()->status)->toBe('submitted');
});

it('preserves values for unchanged steps when selected options are recomputed', function () {
    $builder = new class extends FormBuilder
    {
        public function formType(): string
        {
            return 'application';
        }

        public function mode(): string
        {
            return 'stepper';
        }

        public function steps(): array
        {
            return [[
                'key' => 'applicant',
                'requirements' => [
                    ['key' => 'name', 'type' => 'input', 'rules' => ['required', 'string']],
                ],
            ]];
        }
    };

    $service = app(FormService::class);
    $created = $service->create($builder, [], null, null);
    $service->saveStep($created['form'], 'applicant', ['name' => 'Ada']);

    $service->updateOptions($created['form'], $builder, []);
    $savedStep = $created['form']->refresh()->steps()->firstOrFail();

    expect($savedStep->values)->toBe(['name' => 'Ada'])
        ->and($created['form']->current_step_id)->toBe('review');
});

it('creates and resumes a guest draft through the JSON API with its resume token', function () {
    $builder = new class extends FormBuilder
    {
        public function formType(): string
        {
            return 'api-application';
        }

        public function mode(): string
        {
            return 'stepper';
        }

        public function steps(): array
        {
            return [[
                'key' => 'applicant',
                'requirements' => [
                    ['key' => 'name', 'type' => 'input', 'rules' => ['required', 'string']],
                ],
            ]];
        }
    };

    app(FormBuilderRegistry::class)->register('api-application', $builder);

    $created = $this->postJson('/api/forms', ['type' => 'api-application'])
        ->assertCreated()
        ->assertJsonPath('current_step_id', 'applicant')
        ->json();

    $this->getJson('/api/forms/'.$created['id'])
        ->assertForbidden();

    $this->putJson(
        '/api/forms/'.$created['id'].'/steps/applicant',
        ['values' => ['name' => 'Ada']],
        ['X-Form-Resume-Token' => $created['resume_token']],
    )->assertOk()->assertJsonPath('current_step_id', 'review');

    $this->getJson('/api/forms/'.$created['id'], [
        'X-Form-Resume-Token' => $created['resume_token'],
    ])->assertOk()->assertJsonPath('values.applicant.name', 'Ada');
});

it('stores builder-provided requester values as a draft prefill', function () {
    $builder = new class extends FormBuilder
    {
        public function formType(): string
        {
            return 'prefilled-application';
        }

        public function mode(): string
        {
            return 'stepper';
        }

        public function steps(): array
        {
            return [[
                'key' => 'applicant',
                'requirements' => [
                    ['key' => 'name', 'type' => 'input', 'rules' => ['required', 'string']],
                ],
            ]];
        }

        public function prefillValues(?Model $requester): array
        {
            return ['applicant' => ['name' => $requester?->getAttribute('name')]];
        }
    };
    $requester = new class extends Model {};
    $requester->setAttribute('id', 23);
    $requester->setAttribute('name', 'Ada');

    $created = app(FormService::class)->create(
        $builder,
        [],
        $requester,
        null,
    );

    expect($created['form']->valuesByStep())->toBe(['applicant' => ['name' => 'Ada']]);
});

it('recomputes selected option requirements without discarding compatible saved values', function () {
    $makeOption = static function (int $id, string $key, array $requirements): Model&ProvidesFormRequirements {
        return new class($id, $key, $requirements) extends Model implements ProvidesFormRequirements
        {
            public function __construct(
                private readonly int $optionId = 0,
                private readonly string $optionKey = '',
                private readonly array $optionRequirements = [],
            ) {
                parent::__construct();
            }

            public function formOptionKey(): string
            {
                return $this->optionKey;
            }

            public function formSteps(): array
            {
                return [[
                    'key' => 'profile',
                    'title' => 'Profile',
                    'requirements' => $this->optionRequirements,
                ]];
            }

            public function requires(): array
            {
                return [];
            }

            public function compatibleWith(): array
            {
                return [];
            }

            public function excludes(): array
            {
                return [];
            }

            public function getKey(): mixed
            {
                return $this->optionId;
            }
        };
    };
    $nameOption = $makeOption(1, 'name-option', [
        ['key' => 'name', 'type' => 'input', 'rules' => ['required', 'string']],
    ]);
    $emailOption = $makeOption(2, 'email-option', [
        ['key' => 'name', 'type' => 'input', 'rules' => ['required', 'string']],
        ['key' => 'email', 'type' => 'input', 'rules' => ['required', 'email']],
    ]);
    $builder = new class([$nameOption, $emailOption]) extends FormBuilder
    {
        public function __construct(private readonly array $options) {}

        public function formType(): string
        {
            return 'profile';
        }

        public function mode(): string
        {
            return 'stepper';
        }

        public function resolveOptions(array $keys): array
        {
            return array_values(array_filter(
                $this->options,
                static fn (ProvidesFormRequirements $option): bool => in_array($option->formOptionKey(), $keys, true),
            ));
        }
    };

    $service = app(FormService::class);
    $created = $service->create($builder, ['name-option'], null, null);
    $service->saveStep($created['form'], 'profile', ['name' => 'Ada']);

    $service->updateOptions($created['form'], $builder, ['name-option', 'email-option']);

    expect($created['form']->refresh()->steps()->firstOrFail()->values)->toBe(['name' => 'Ada'])
        ->and($created['form']->current_step_id)->toBe('profile');
});

it('fills missing guest draft values after login without overwriting entered values', function () {
    $builder = new class extends FormBuilder
    {
        public function formType(): string
        {
            return 'claimable-application';
        }

        public function mode(): string
        {
            return 'stepper';
        }

        public function steps(): array
        {
            return [[
                'key' => 'applicant',
                'requirements' => [
                    ['key' => 'name', 'type' => 'input', 'rules' => ['required', 'string']],
                    ['key' => 'email', 'type' => 'input', 'rules' => ['nullable', 'email']],
                ],
            ]];
        }

        public function prefillValues(?Model $requester): array
        {
            return $requester === null
                ? []
                : ['applicant' => ['name' => 'Profile Name', 'email' => 'profile@example.test']];
        }
    };
    $service = app(FormService::class);
    $created = $service->create($builder, [], null, null);
    $service->saveStep($created['form'], 'applicant', ['name' => 'Entered Name']);

    $requester = new class extends Model {};
    $requester->setAttribute('id', 24);
    $service->claimGuest($created['form'], $requester, $builder);

    expect($created['form']->refresh()->valuesByStep())->toBe([
        'applicant' => [
            'name' => 'Entered Name',
            'email' => 'profile@example.test',
        ],
    ]);
});

it('adds authenticated-only steps when a guest draft is claimed after login', function () {
    $builder = new class extends FormBuilder
    {
        public function formType(): string
        {
            return 'guest-auth-application';
        }

        public function mode(): string
        {
            return 'stepper';
        }

        public function steps(): array
        {
            return [
                [
                    'key' => 'guest-details',
                    'guest-scope' => ['guest'],
                    'requirements' => [
                        ['key' => 'description', 'type' => 'input', 'rules' => ['required', 'string']],
                    ],
                ],
                [
                    'key' => 'account-details',
                    'guest-scope' => ['authenticated'],
                    'requires-authentication' => true,
                    'requirements' => [
                        ['key' => 'email', 'type' => 'input', 'rules' => ['required', 'email']],
                    ],
                ],
            ];
        }

        public function prefillValues(?Model $requester): array
        {
            return $requester === null
                ? []
                : ['account-details' => ['email' => 'ada@example.test']];
        }
    };
    $service = app(FormService::class);
    $created = $service->create($builder, [], null, null);
    $service->saveStep($created['form'], 'guest-details', ['description' => 'Need a permit']);

    expect(array_column($created['form']->definition['steps'], 'key'))->toBe(['guest-details']);
    expect($created['result']->toArray()['authentication_required'])->toBeTrue()
        ->and(fn () => $service->complete(Form::query()->findOrFail($created['form']->getKey())))
        ->toThrow(AuthorizationException::class);

    $requester = new class extends Model {};
    $requester->setAttribute('id', 25);
    $service->claimGuest($created['form'], $requester, $builder);

    $claimed = Form::query()->findOrFail($created['form']->getKey());

    expect(array_column($claimed->definition['steps'], 'key'))
        ->toBe(['account-details'])
        ->and($claimed->current_step_id)->toBe('account-details')
        ->and($claimed->valuesByStep()['account-details']['email'])->toBe('ada@example.test');
});

it('does not require authentication for steps outside the active form type scope', function () {
    $builder = new class extends FormBuilder
    {
        public function formType(): string
        {
            return 'guest-application';
        }

        public function mode(): string
        {
            return 'stepper';
        }

        public function steps(): array
        {
            return [
                ['key' => 'guest-details'],
                [
                    'key' => 'other-details',
                    'requires-authentication' => true,
                    'types-scope' => ['account-application'],
                ],
            ];
        }
    };

    $service = app(FormService::class);
    $created = $service->create($builder, [], null, null);
    $service->saveStep($created['form'], 'guest-details', []);

    expect($created['result']->toArray()['authentication_required'])->toBeFalse()
        ->and($service->complete(Form::query()->findOrFail($created['form']->getKey()))->toArray()['status'])
        ->toBe('submitted');
});

it('rejects values outside the active input schema without advancing the draft', function () {
    $builder = new class extends FormBuilder
    {
        public function formType(): string
        {
            return 'strict-application';
        }

        public function mode(): string
        {
            return 'stepper';
        }

        public function steps(): array
        {
            return [[
                'key' => 'applicant',
                'requirements' => [
                    ['key' => 'name', 'type' => 'input', 'rules' => ['required', 'string']],
                ],
            ]];
        }
    };
    $created = app(FormService::class)->create($builder, [], null, null);

    expect(fn () => app(FormService::class)->saveStep(
        $created['form'],
        'applicant',
        ['name' => 'Ada', 'admin' => true],
    ))->toThrow(ValidationException::class);

    expect($created['form']->refresh()->current_step_id)->toBe('applicant')
        ->and($created['form']->steps()->count())->toBe(0);
});

it('requires authentication on explicitly gated steps', function () {
    $builder = new class extends FormBuilder
    {
        public function formType(): string
        {
            return 'authenticated-application';
        }

        public function mode(): string
        {
            return 'stepper';
        }

        public function steps(): array
        {
            return [[
                'key' => 'account',
                'requires-authentication' => true,
                'requirements' => [
                    ['key' => 'email', 'type' => 'input', 'rules' => ['required', 'email']],
                ],
            ]];
        }
    };
    $created = app(FormService::class)->create($builder, [], null, null);

    expect(fn () => app(FormService::class)->saveStep(
        $created['form'],
        'account',
        ['email' => 'ada@example.test'],
    ))->toThrow(AuthorizationException::class);
});

it('stores and completes all step values for a single-mode form in one submission', function () {
    $builder = new class extends FormBuilder
    {
        public function formType(): string
        {
            return 'single-application';
        }

        public function mode(): string
        {
            return 'single';
        }

        public function steps(): array
        {
            return [[
                'key' => 'applicant',
                'requirements' => [
                    ['key' => 'name', 'type' => 'input', 'rules' => ['required', 'string']],
                ],
            ]];
        }
    };
    $created = app(FormService::class)->create($builder, [], null, null);

    app(FormService::class)->submitSingle($created['form'], [
        'applicant' => ['name' => 'Ada'],
    ]);

    expect($created['form']->refresh()->status)->toBe('submitted')
        ->and($created['form']->valuesByStep())->toBe(['applicant' => ['name' => 'Ada']]);
});
