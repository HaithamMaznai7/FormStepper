<?php

declare(strict_types=1);

use HaithamMaznai\FormStepper\Forms\FormBuilder;
use HaithamMaznai\FormStepper\Forms\FormBuilderRegistry;
use HaithamMaznai\FormStepper\FormStepperServiceProvider;
use HaithamMaznai\FormStepper\Models\Form;
use HaithamMaznai\FormStepper\Models\FormInput;
use HaithamMaznai\FormStepper\Models\FormInputType;
use HaithamMaznai\FormStepper\Models\FormStepTemplate;
use HaithamMaznai\FormStepper\Schema\Input;
use HaithamMaznai\FormStepper\Services\FormService;
use HaithamMaznai\FormStepper\Support\InputTypeRegistry;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AdminTestUser extends Authenticatable
{
    protected $table = 'admin_users';

    protected $guarded = [];
}

class AdminTestBuilder extends FormBuilder
{
    public function formType(): string
    {
        return 'admin-application';
    }

    public function mode(): string
    {
        return 'stepper';
    }

    public function startWithSteps(): array
    {
        return [
            [
                'key' => 'applicant',
                'title' => 'Applicant',
                'requirements' => [
                    ['key' => 'name', 'type' => 'input', 'rules' => ['required', 'string']],
                    ['key' => 'age', 'type' => 'input', 'rules' => ['required', 'integer']],
                ],
            ],
            [
                'key' => 'contact',
                'requirements' => [
                    ['key' => 'email', 'type' => 'input', 'rules' => ['required', 'email']],
                ],
            ],
        ];
    }
}

function loadAdminRoutes(): void
{
    Route::setRoutes(new RouteCollection);
    require __DIR__.'/../../routes/form-stepper-admin.php';
    Route::get('/login', fn () => 'login')->name('login');
    Route::getRoutes()->refreshNameLookups();
}

beforeEach(function () {
    InputTypeRegistry::flush();
    Schema::create('admin_users', function (Blueprint $table) {
        $table->id();
        $table->boolean('is_admin')->default(false);
        $table->timestamps();
    });
    foreach (['2026_01_01_000000_create_forms_table.php', '2026_10_07_000000_create_form_library_tables.php'] as $file) {
        (require __DIR__.'/../../database/migrations/'.$file)->up();
    }

    config()->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
    config()->set('form-stepper.admin.enabled', true);
    config()->set('auth.providers.users.model', AdminTestUser::class);
    Gate::define('manage-form-stepper', fn (AdminTestUser $user): bool => (bool) $user->is_admin);
    app(FormBuilderRegistry::class)->register('admin-application', new AdminTestBuilder);
    loadAdminRoutes();

    $this->admin = AdminTestUser::create(['is_admin' => true]);
});

afterEach(fn () => InputTypeRegistry::flush());

it('keeps admin routes disabled by default', function () {
    config()->set('form-stepper.admin.enabled', false);
    loadAdminRoutes();

    expect(Route::has('form-stepper.admin.forms.index'))->toBeFalse()
        ->and(config('form-stepper.admin.middleware'))->toBe(['web', 'auth']);
});

it('requires authentication and the configured gate', function () {
    $this->get(route('form-stepper.admin.inputs.index'))->assertRedirect();

    $this->actingAs(AdminTestUser::create())
        ->get(route('form-stepper.admin.inputs.index'))
        ->assertForbidden();

    $this->actingAs($this->admin)
        ->get(route('form-stepper.admin.inputs.index'))
        ->assertOk()
        ->assertSee('Lookup inputs');
});

it('seeds protected system input types and manages custom input types', function () {
    $this->actingAs($this->admin);
    $complex = FormInputType::query()->where('key', 'complex')->firstOrFail();

    expect(FormInputType::query()->where('is_system', true)->pluck('key')->all())
        ->toEqualCanonicalizing(InputTypeRegistry::systemKeys())
        ->and(InputTypeRegistry::supports('signature'))->toBeFalse();

    $this->get(route('form-stepper.admin.input-types.index'))->assertOk()->assertSee('complex');
    $this->delete(route('form-stepper.admin.input-types.destroy', $complex))->assertSessionHasErrors('type');
    $this->put(route('form-stepper.admin.input-types.update', $complex), ['key' => 'renamed', 'name' => 'Group'])
        ->assertSessionHasErrors('key');
    $this->put(route('form-stepper.admin.input-types.update', $complex), ['name' => 'Group'])->assertRedirect();
    expect($complex->refresh()->name)->toBe('Group')->and($complex->has_children)->toBeTrue();

    $this->post(route('form-stepper.admin.input-types.store'), [
        'key' => 'signature',
        'name' => 'Signature',
        'has_options' => '1',
    ])->assertRedirect();
    $signature = FormInputType::query()->where('key', 'signature')->firstOrFail();

    expect($signature->is_system)->toBeFalse()
        ->and($signature->has_options)->toBeTrue()
        ->and(InputTypeRegistry::supports('signature'))->toBeTrue()
        ->and(Input::make('sign', 'signature')->toArray()['type'])->toBe('signature');

    $this->get(route('form-stepper.admin.input-types.show', $signature))->assertOk()->assertSee('Signature');
    $this->get(route('form-stepper.admin.input-types.edit', $signature))->assertOk();
    $this->get(route('form-stepper.admin.input-types.create'))->assertOk();

    FormInput::create(['key' => 'sign', 'type' => 'signature']);
    $this->delete(route('form-stepper.admin.input-types.destroy', $signature))->assertSessionHasErrors('type');
    FormInput::query()->where('key', 'sign')->delete();
    $this->delete(route('form-stepper.admin.input-types.destroy', $signature))->assertRedirect();

    expect(FormInputType::query()->whereKey($signature->id)->exists())->toBeFalse()
        ->and(InputTypeRegistry::supports('signature'))->toBeFalse();
});

it('manages lookup inputs with choices, rules, defaults and complex children', function () {
    $this->actingAs($this->admin);
    $this->get(route('form-stepper.admin.inputs.create'))->assertOk();

    $this->post(route('form-stepper.admin.inputs.store'), [
        'key' => 'car_type',
        'type' => 'single-selection',
        'rules' => "required|string\nregex:/^[a-z|]+$/",
        'options' => "sedan|Sedan\nsuv",
        'default_value' => 'sedan',
        'extra' => '{"icon": "car"}',
    ])->assertRedirect();
    $carType = FormInput::query()->where('key', 'car_type')->firstOrFail();

    expect($carType->rules)->toBe(['required', 'string', 'regex:/^[a-z|]+$/'])
        ->and($carType->options)->toBe([['value' => 'sedan', 'label' => 'Sedan'], ['value' => 'suv', 'label' => 'suv']])
        ->and($carType->toInput()->toArray())->toMatchArray([
            'key' => 'car_type',
            'type' => 'single-selection',
            'value' => 'sedan',
            'extra' => ['icon' => 'car', 'options' => $carType->options],
        ]);

    $this->post(route('form-stepper.admin.inputs.store'), ['key' => 'plate_no', 'type' => 'plate', 'rules' => 'required']);
    $this->post(route('form-stepper.admin.inputs.store'), [
        'key' => 'vehicle',
        'type' => 'complex',
        'children' => [$carType->id, FormInput::query()->where('key', 'plate_no')->value('id')],
    ])->assertRedirect();
    $vehicle = FormInput::query()->where('key', 'vehicle')->firstOrFail();

    expect(array_column($vehicle->toInput()->toArray()['requirements'], 'key'))->toBe(['car_type', 'plate_no']);

    $this->put(route('form-stepper.admin.inputs.update', $carType), [
        'key' => 'car_type',
        'type' => 'complex',
        'children' => [$vehicle->id],
    ])->assertSessionHasErrors('children');
    $this->post(route('form-stepper.admin.inputs.store'), ['key' => 'bad', 'type' => 'input', 'extra' => '[1]'])
        ->assertSessionHasErrors('extra');
    $this->post(route('form-stepper.admin.inputs.store'), ['key' => 'bad', 'type' => 'unknown'])
        ->assertSessionHasErrors('type');

    $this->get(route('form-stepper.admin.inputs.index', ['q' => 'car']))->assertOk()->assertSee('car_type')->assertDontSee('plate_no');
    $this->get(route('form-stepper.admin.inputs.show', $vehicle))->assertOk()->assertSee('plate_no');
    $this->get(route('form-stepper.admin.inputs.edit', $vehicle))->assertOk();
    $this->delete(route('form-stepper.admin.inputs.destroy', $vehicle))->assertRedirect();

    expect(FormInput::query()->whereKey($vehicle->id)->exists())->toBeFalse();
});

it('manages ordered library steps that snapshot lookup inputs', function () {
    $this->actingAs($this->admin);
    $name = FormInput::create(['key' => 'name', 'label' => 'Name', 'type' => 'input', 'rules' => ['required']]);
    $email = FormInput::create(['key' => 'email', 'type' => 'input', 'rules' => ['email']]);
    $this->get(route('form-stepper.admin.steps.create'))->assertOk()->assertSee('email');

    $this->post(route('form-stepper.admin.steps.store'), [
        'key' => 'contact',
        'priority' => 1,
        'inputs' => [$name->id, $email->id],
        'positions' => [$name->id => 2, $email->id => 1],
        'requires_authentication' => '1',
    ])->assertRedirect();
    $step = FormStepTemplate::query()->where('key', 'contact')->firstOrFail();
    $snapshot = $step->toStep()->toArray();

    expect(array_column($snapshot['requirements'], 'key'))->toBe(['email', 'name'])
        ->and($snapshot)->toMatchArray(['priority' => 1, 'requires-authentication' => true, 'repeatable' => false])
        ->and($snapshot)->not->toHaveKey('title');

    $this->put(route('form-stepper.admin.steps.update', $step), [
        'key' => 'contact',
        'inputs' => [$name->id],
        'repeatable' => '1',
    ])->assertSessionHasErrors('repeat_name');
    $this->put(route('form-stepper.admin.steps.update', $step), [
        'key' => 'contact',
        'inputs' => [$name->id],
        'repeatable' => '1',
        'repeat_name' => 'people',
    ])->assertRedirect();

    expect($step->refresh()->toStep()->toArray())->toMatchArray(['repeatable' => true, 'repeat-name' => 'people'])
        ->and($step->inputs->pluck('key')->all())->toBe(['name']);

    $this->post(route('form-stepper.admin.steps.store'), ['key' => 'review', 'inputs' => [$name->id]])
        ->assertSessionHasErrors('key');
    $this->get(route('form-stepper.admin.steps.index'))->assertOk()->assertSee('contact');
    $this->get(route('form-stepper.admin.steps.show', $step))->assertOk()->assertSee('people');
    $this->get(route('form-stepper.admin.steps.edit', $step))->assertOk();
    $this->delete(route('form-stepper.admin.steps.destroy', $step))->assertRedirect();

    expect(FormStepTemplate::query()->count())->toBe(0)
        ->and(FormInput::query()->count())->toBe(2);
});

it('lists, creates, edits values of, and deletes forms', function () {
    $this->actingAs($this->admin);
    $submitted = app(FormService::class)->create(new AdminTestBuilder, [], null, null)['form'];
    $submitted->update(['status' => 'submitted', 'completed_at' => now()]);

    $this->get(route('form-stepper.admin.forms.create'))->assertOk()->assertSee('admin-application');
    $this->post(route('form-stepper.admin.forms.store'), ['type' => 'admin-application'])->assertRedirect();
    $form = Form::query()->where('status', 'draft')->firstOrFail();

    expect($form->requester?->is($this->admin))->toBeTrue()
        ->and($form->current_step_id)->toBe('applicant');

    $this->get(route('form-stepper.admin.forms.index', ['status' => 'draft']))
        ->assertOk()
        ->assertSee(substr($form->uuid, 0, 12))
        ->assertDontSee(substr($submitted->uuid, 0, 12));
    $this->get(route('form-stepper.admin.forms.edit', $form))->assertOk()->assertSee('Save step');

    $this->put(route('form-stepper.admin.forms.steps.update', [$form, 'contact']), ['values' => ['email' => 'ada@example.test']])
        ->assertRedirect();
    expect($form->refresh()->current_step_id)->toBe('applicant');

    $this->put(route('form-stepper.admin.forms.steps.update', [$form, 'applicant']), ['values' => ['name' => 'Ada', 'age' => 'x']])
        ->assertSessionHasErrors('age');
    $this->put(route('form-stepper.admin.forms.steps.update', [$form, 'applicant']), ['values' => ['name' => 'Ada', 'age' => '36']])
        ->assertRedirect();

    expect($form->refresh()->current_step_id)->toBe('review')
        ->and($form->valuesByStep())->toEqualCanonicalizing([
            'contact' => ['email' => 'ada@example.test'],
            'applicant' => ['name' => 'Ada', 'age' => '36'],
        ]);

    $this->put(route('form-stepper.admin.forms.steps.update', [$form, 'missing']), ['values' => []])
        ->assertSessionHasErrors('step');
    $this->put(route('form-stepper.admin.forms.update', $form), ['options' => 'unknown'])
        ->assertSessionHasErrors('options');
    $this->get(route('form-stepper.admin.forms.show', $form))->assertOk()->assertSee('Ada');
    $this->get(route('form-stepper.admin.forms.edit', $submitted))->assertRedirect(route('form-stepper.admin.forms.show', $submitted));

    $this->delete(route('form-stepper.admin.forms.destroy', $form))->assertRedirect();
    expect(Form::query()->whereKey($form->id)->exists())->toBeFalse();
});

it('publishes admin controllers and selects configured controllers', function () {
    $paths = ServiceProvider::pathsToPublish(FormStepperServiceProvider::class, 'form-stepper-admin-controllers');

    expect(array_values($paths))->toBe([
        app_path('Http/Controllers/FormStepper/Admin/InputTypeController.php'),
        app_path('Http/Controllers/FormStepper/Admin/InputController.php'),
        app_path('Http/Controllers/FormStepper/Admin/StepTemplateController.php'),
        app_path('Http/Controllers/FormStepper/Admin/FormController.php'),
    ]);

    foreach (array_keys($paths) as $stub) {
        expect(file_get_contents($stub))->toContain('namespace App\Http\Controllers\FormStepper\Admin;', 'extends Base');
    }

    expect(array_values(ServiceProvider::pathsToPublish(FormStepperServiceProvider::class, 'form-stepper-views')))
        ->toBe([resource_path('views/vendor/form-stepper')]);

    config()->set('form-stepper.admin.controllers.inputs', 'App\Http\Controllers\FormStepper\Admin\InputController');
    loadAdminRoutes();

    expect(Route::getRoutes()->getByName('form-stepper.admin.inputs.index')->getActionName())
        ->toBe('App\Http\Controllers\FormStepper\Admin\InputController@index')
        ->and(Route::getRoutes()->getByName('form-stepper.admin.steps.index')->getActionName())
        ->toStartWith('HaithamMaznai\FormStepper\Http\Controllers\Admin\StepTemplateController@');
});
