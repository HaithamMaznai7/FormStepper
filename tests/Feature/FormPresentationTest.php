<?php

declare(strict_types=1);

use HaithamMaznai\FormStepper\Contracts\ProvidesFormRequirements;
use HaithamMaznai\FormStepper\Forms\FormBuilder;
use HaithamMaznai\FormStepper\Forms\FormBuilderRegistry;
use HaithamMaznai\FormStepper\Forms\FormResult;
use HaithamMaznai\FormStepper\FormStepperServiceProvider;
use HaithamMaznai\FormStepper\Models\Form;
use HaithamMaznai\FormStepper\Services\FormService;
use HaithamMaznai\FormStepper\Support\FormPresentation;
use HaithamMaznai\FormStepper\Support\SchemaAssembler;
use HaithamMaznai\FormStepper\Support\SchemaNormalizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

beforeEach(function () {
    (require __DIR__.'/../../database/migrations/2026_01_01_000000_create_forms_table.php')->up();
});

it('loads and publishes the locale file for input and step text', function () {
    expect(__('form-stepper::forms.defaults.steps.review.title'))->toBe('Review');
    $paths = ServiceProvider::pathsToPublish(FormStepperServiceProvider::class, 'form-stepper-lang');
    expect(array_values($paths))->toBe([app()->langPath('vendor/form-stepper')])
        ->and(is_file(array_key_first($paths).'/en/forms.php'))->toBeTrue();
});

it('sorts priorities stably with negative ranks after positive ranks', function () {
    $builder = new class extends FormBuilder
    {
        public function formType(): string
        {
            return 'priority';
        }

        public function startWithSteps(): array
        {
            return [
                ['key' => 'last', 'priority' => -1],
                ['key' => 'hundred', 'priority' => 100],
                ['key' => 'earlier_negative', 'priority' => -10],
                ['key' => 'first', 'priority' => 1],
                ['key' => 'default'],
                ['key' => 'zero', 'priority' => 0],
            ];
        }
    };
    $created = app(FormService::class)->create($builder, [], null, null);
    expect(array_column($created['form']->definition['steps'], 'key'))
        ->toBe(['zero', 'first', 'hundred', 'default', 'earlier_negative', 'last'])
        ->and($created['form']->current_step_id)->toBe('zero');
    $steps = $created['result']->toArray()['definition']['steps'];
    expect(array_column($steps, 'key'))->toBe(['zero', 'first', 'hundred', 'default', 'earlier_negative', 'last', 'review']);
});

it('rejects invalid and conflicting priorities', function () {
    expect(fn () => (new SchemaNormalizer)->normalize([['key' => 'bad', 'priority' => '1']]))
        ->toThrow(InvalidArgumentException::class);
    $builder = new class extends FormBuilder
    {
        public function formType(): string
        {
            return 'priority';
        }

        public function startWithSteps(): array
        {
            return [['key' => 'same', 'priority' => 1], ['key' => 'same', 'priority' => 2]];
        }
    };
    expect(fn () => app(SchemaAssembler::class)->assemble($builder, [], null, null, true))
        ->toThrow(InvalidArgumentException::class, 'priority');
});

it('reports the removed builder hook instead of silently ignoring legacy steps', function () {
    $builder = new class extends FormBuilder
    {
        public function formType(): string
        {
            return 'legacy';
        }

        public function steps(): array
        {
            return [['key' => 'legacy']];
        }
    };
    expect(fn () => app(SchemaAssembler::class)->assemble($builder, [], null, null, true))
        ->toThrow(InvalidArgumentException::class, 'startWithSteps()');
});

it('keeps start option and end groups ordered and merges shared keys into their first group', function () {
    $builder = new class extends FormBuilder
    {
        public function formType(): string
        {
            return 'boundaries';
        }

        public function startWithSteps(): array
        {
            return [
                ['key' => 'start_last', 'priority' => -1],
                ['key' => 'shared', 'priority' => 100, 'requirements' => [['key' => 'start', 'type' => 'input']]],
            ];
        }

        public function endWithSteps(): array
        {
            return [
                ['key' => 'end', 'priority' => 1],
                ['key' => 'shared', 'priority' => 100, 'requirements' => [['key' => 'end', 'type' => 'input']]],
            ];
        }
    };
    $option = new class extends Model implements ProvidesFormRequirements
    {
        public function formOptionKey(): string
        {
            return 'option';
        }

        public function formSteps(): array
        {
            return [
                ['key' => 'option_last', 'priority' => -1],
                ['key' => 'option_first', 'priority' => 1],
                ['key' => 'shared', 'priority' => 100, 'requirements' => [['key' => 'option', 'type' => 'input']]],
            ];
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
    };
    $definition = app(SchemaAssembler::class)->assemble($builder, [$option], null, null, true);
    expect(array_column($definition->steps, 'key'))->toBe(['shared', 'start_last', 'option_first', 'option_last', 'end'])
        ->and(array_column($definition->steps[0]['requirements'], 'key'))->toBe(['start', 'option', 'end'])
        ->and(method_exists($builder, 'steps'))->toBeFalse();
});

it('renders builder defaults without persisting them in either form mode', function (string $mode) {
    $builder = new class($mode) extends FormBuilder
    {
        public function __construct(private string $formMode) {}

        public function formType(): string
        {
            return 'defaults';
        }

        public function mode(): string
        {
            return $this->formMode;
        }

        public function startWithSteps(): array
        {
            return [
                ['key' => 'person', 'title' => 'Old title', 'requirements' => [
                    ['key' => 'name', 'type' => 'input', 'label' => 'Old label', 'placeholder' => 'Old placeholder'],
                    ['key' => 'address', 'type' => 'complex', 'children' => [
                        ['key' => 'city', 'type' => 'input'],
                    ]],
                ]],
                ['key' => 'people', 'repeatable' => true, 'repeat_name' => 'people', 'requirements' => [
                    ['key' => 'name', 'type' => 'input'],
                ]],
            ];
        }

        public function defaultValues(?Model $requester = null, ?Model $tenant = null): array
        {
            return ['person' => ['name' => 'Ada', 'address' => ['city' => 'Riyadh']], 'people' => ['name' => 'Guest']];
        }
    };
    app(FormBuilderRegistry::class)->register('defaults', $builder);
    $created = app(FormService::class)->create($builder, [], null, null);
    $form = $created['form'];
    $groups = $created['result']->toArray()['definition'][$mode === 'single' ? 'containers' : 'steps'];
    expect($groups[0]['requirements'][0]['default'])->toBe('Ada')
        ->and($groups[0]['requirements'][0]['value'])->toBeNull()
        ->and($groups[0]['values'])->toBeNull()
        ->and($groups[0]['requirements'][1]['children'][0]['default'])->toBe('Riyadh')
        ->and($groups[1]['requirements'][0]['default'])->toBe('Guest')
        ->and($form->steps()->count())->toBe(0)
        ->and($form->definition['steps'][0])->not->toHaveKey('title')
        ->and($form->definition['steps'][0]['requirements'][0])->not->toHaveKey('label');
    $form->steps()->create(['step_key' => 'person', 'values' => ['name' => 'Saved'], 'saved_at' => now()]);
    $rendered = (new FormResult($form->fresh()))->toArray()['definition'][$mode === 'single' ? 'containers' : 'steps'];
    expect($rendered[0]['requirements'][0]['value'])->toBe('Saved')
        ->and($rendered[0]['requirements'][0]['default'])->toBe('Ada');
})->with(['single', 'stepper']);

it('uses contextual translations and resolves locale on each render', function () {
    $builder = new class extends FormBuilder
    {
        public function formType(): string
        {
            return 'localized';
        }

        public function startWithSteps(): array
        {
            return [['key' => 'contact', 'requirements' => [['key' => 'name', 'type' => 'input']]]];
        }
    };
    $created = app(FormService::class)->create($builder, [], null, null);
    $prefix = 'forms.contexts.localized.stepper.guest.none';
    Lang::addLines([
        "{$prefix}.steps.contact.title" => 'Contact details',
        "{$prefix}.steps.contact.subtitle" => 'Your details',
        "{$prefix}.inputs.name.label" => 'Full name',
        "{$prefix}.inputs.name.placeholder" => 'Enter your name',
    ], 'en', 'form-stepper');
    Lang::addLines(['forms.defaults.inputs.name.label' => 'Arabic name'], 'ar', 'form-stepper');
    app()->setLocale('en');
    $group = $created['result']->toArray()['definition']['steps'][0];
    expect($group['title'])->toBe('Contact details')
        ->and($group['subtitle'])->toBe('Your details')
        ->and($group['requirements'][0]['label'])->toBe('Full name')
        ->and($group['requirements'][0]['placeholder'])->toBe('Enter your name');
    app()->setLocale('ar');
    expect($created['result']->toArray()['definition']['steps'][0]['requirements'][0]['label'])->toBe('Arabic name');
});

it('isolates translations by form mode and stored morph identities', function () {
    $form = new Form([
        'type' => 'order',
        'mode' => 'single',
        'requester_type' => 'customer',
        'tenant_type' => 'App\\Models\\Team',
    ]);
    $tenantKey = rawurlencode('App\\Models\\Team');
    Lang::addLines([
        "forms.contexts.order.single.customer.{$tenantKey}.inputs.email.label" => 'Tenant customer email',
        'forms.contexts.order.stepper.customer.none.inputs.email.label' => 'Stepper email',
        'forms.defaults.inputs.email.label' => 'Email address',
    ], 'en', 'form-stepper');
    $presentation = app(FormPresentation::class);
    $step = ['key' => 'contact', 'requirements' => [['key' => 'email', 'type' => 'input']]];
    expect($presentation->step($form, $step, [])['requirements'][0]['label'])->toBe('Tenant customer email');
    $form->tenant_type = null;
    expect($presentation->step($form, $step, [])['requirements'][0]['label'])->toBe('Email address');
    $form->mode = 'stepper';
    expect($presentation->step($form, $step, [])['requirements'][0]['label'])->toBe('Stepper email');
});

it('uses readable keys and null optional text when no translation exists', function () {
    $form = new Form(['type' => 'unknown', 'mode' => 'single']);
    $step = app(FormPresentation::class)->step($form, [
        'key' => 'contact_details',
        'title' => 'Ignored',
        'requirements' => [['key' => 'first_name', 'type' => 'input', 'label' => 'Ignored']],
    ], []);
    expect($step['title'])->toBe('Contact Details')
        ->and($step['subtitle'])->toBeNull()
        ->and($step['requirements'][0]['label'])->toBe('First Name')
        ->and($step['requirements'][0]['placeholder'])->toBeNull();
});

it('adds priority to existing library tables without deleting legacy display text', function () {
    config()->set('form-stepper.tables.step_templates', 'custom_steps');
    Schema::create('custom_steps', function (Blueprint $table): void {
        $table->id();
        $table->string('key');
        $table->string('title')->nullable();
    });
    DB::table('custom_steps')->insert(['key' => 'contact', 'title' => 'Legacy title']);
    $migration = require __DIR__.'/../../database/migrations/2026_10_08_000000_add_library_step_priority.php';
    $migration->up();
    $migration->up();
    $record = DB::table('custom_steps')->first();
    expect($record->priority)->toBe(100)->and($record->title)->toBe('Legacy title');
});

it('keeps builder defaults on saved repeat instances and recalculates them on every render', function () {
    $builder = new class extends FormBuilder
    {
        public string $displayName = 'First';

        public function formType(): string
        {
            return 'repeat-default';
        }

        public function startWithSteps(): array
        {
            return [['key' => 'people', 'repeatable' => true, 'repeat_name' => 'people',
                'requirements' => [['key' => 'name', 'type' => 'input']]]];
        }

        public function defaultValues(?Model $requester = null, ?Model $tenant = null): array
        {
            return ['people' => ['name' => $this->displayName]];
        }
    };
    app(FormBuilderRegistry::class)->register($builder->formType(), $builder);
    $created = app(FormService::class)->create($builder, [], null, null);
    $created['form']->steps()->create(['step_key' => 'people', 'values' => ['people' => [['name' => 'Saved']]], 'saved_at' => now()]);
    $result = new FormResult($created['form']->fresh());
    $instance = $result->toArray()['definition']['steps'][0]['repeats'][0]['requirements'][0];
    expect($instance['default'])->toBe('First')->and($instance['value'])->toBe('Saved');
    $builder->displayName = 'Second';
    expect($result->toArray()['definition']['steps'][0]['repeats'][0]['requirements'][0]['default'])->toBe('Second')
        ->and($created['form']->fresh()->steps()->first()->values)->toBe(['people' => [['name' => 'Saved']]]);
});
