<?php

declare(strict_types=1);

use FormStepper\FormStepper\Contracts\TenantForm;
use FormStepper\FormStepper\Forms\FormBuilder;
use FormStepper\FormStepper\Forms\FormBuilderRegistry;
use FormStepper\FormStepper\Models\Form;
use FormStepper\FormStepper\Services\FormService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;

class FormOwnershipTenant extends Model implements TenantForm
{
    protected $table = 'ownership_tenants';

    protected $guarded = [];

    /** @return MorphMany<Form, $this> */
    public function forms(): MorphMany
    {
        return $this->morphMany(Form::class, 'tenant');
    }
}

class FormOwnershipUser extends Authenticatable
{
    protected $table = 'ownership_users';

    protected $guarded = [];

    /** @return BelongsTo<FormOwnershipTenant, $this> */
    public function currentTenant(): BelongsTo
    {
        return $this->belongsTo(FormOwnershipTenant::class, 'current_tenant_id');
    }
}

beforeEach(function () {
    Schema::create('ownership_tenants', function (Blueprint $table) {
        $table->id();
        $table->timestamps();
    });
    Schema::create('ownership_users', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('current_tenant_id')->nullable();
        $table->timestamps();
    });
    $this->builder = new class extends FormBuilder
    {
        public function formType(): string
        {
            return 'ownership';
        }
    };
    app(FormBuilderRegistry::class)->register('ownership', $this->builder);
});

function migrateOwnershipForms(bool $tenantEnabled): void
{
    config()->set('form-stepper.tenant.enabled', $tenantEnabled);
    $migration = require __DIR__.'/../../database/migrations/2026_01_01_000000_create_forms_table.php';
    $migration->up();
}

function ownershipRequest(?Model $user): Request
{
    $request = Request::create('/');
    $request->setUserResolver(fn () => $user);

    return $request;
}

it('keeps requester columns without creator or tenant columns when tenants are disabled', function () {
    migrateOwnershipForms(false);
    $user = FormOwnershipUser::create();
    $created = app(FormService::class)->create($this->builder, [], $user, null);

    expect(Schema::hasColumn('forms', 'requester_type'))->toBeTrue()
        ->and(Schema::hasColumn('forms', 'creator_id'))->toBeFalse()
        ->and(Schema::hasColumn('forms', 'tenant_id'))->toBeFalse()
        ->and($created['form']->requester->is($user))->toBeTrue();
});

it('uses the requester and current tenant to scope and authorize forms', function () {
    migrateOwnershipForms(true);
    $tenant = FormOwnershipTenant::create();
    $user = FormOwnershipUser::create(['current_tenant_id' => $tenant->id]);
    $otherUser = FormOwnershipUser::create(['current_tenant_id' => $tenant->id]);
    $service = app(FormService::class);
    $personal = $service->create($this->builder, [], $user, null)['form'];
    $owned = $service->create($this->builder, [], $user, $tenant)['form'];
    $other = $service->create($this->builder, [], $otherUser, $tenant)['form'];
    $request = ownershipRequest($user);

    expect($this->builder->tenant($request)?->is($tenant))->toBeTrue()
        ->and($tenant->forms()->count())->toBe(2)
        ->and($this->builder->scopeForms(Form::query(), $request)->pluck('id')->all())->toBe([$owned->id]);
    $this->builder->authorize('view', $request, $owned);
    expect(fn () => $this->builder->authorize('view', $request, $other))
        ->toThrow(HttpException::class);
    expect(fn () => $this->builder->authorize('view', $request, $personal))
        ->toThrow(HttpException::class);

    $user->current_tenant_id = null;
    $user->unsetRelation('currentTenant');
    expect($this->builder->tenant($request))->toBeNull()
        ->and($this->builder->scopeForms(Form::query(), $request)->pluck('id')->all())->toBe([$personal->id]);
});

it('creates tenant forms through the API and claims guest drafts in the current tenant', function () {
    migrateOwnershipForms(true);
    $guest = $this->postJson('/api/forms', ['type' => 'ownership'])->assertCreated()->json();
    $tenant = FormOwnershipTenant::create();
    $user = FormOwnershipUser::create(['current_tenant_id' => $tenant->id]);
    $this->app['auth']->guard()->setUser($user);

    $created = $this->postJson('/api/forms', ['type' => 'ownership'])->assertCreated()->json();
    $this->getJson('/api/forms/'.$guest['id'], [
        'X-Form-Resume-Token' => $guest['resume_token'],
    ])->assertOk();

    foreach ([$guest['id'], $created['id']] as $uuid) {
        $form = Form::query()->where('uuid', $uuid)->firstOrFail();
        expect($form->requester->is($user))->toBeTrue()
            ->and($form->tenant->is($tenant))->toBeTrue();
    }
});

it('lists only a token-matched guest form with null identities', function () {
    migrateOwnershipForms(true);
    $first = $this->postJson('/api/forms', ['type' => 'ownership'])->assertCreated()->json();
    $this->postJson('/api/forms', ['type' => 'ownership'])->assertCreated();
    $this->getJson('/api/forms?type=ownership')->assertForbidden();
    $this->getJson('/api/forms?type=ownership', ['X-Form-Resume-Token' => 'invalid'])->assertForbidden();
    $this->getJson('/api/forms?type=ownership', [
        'X-Form-Resume-Token' => $first['resume_token'],
    ])->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $first['id']);

    expect(Form::query()->whereNotNull('tenant_id')->count())->toBe(0)
        ->and(Form::query()->whereNotNull('requester_id')->count())->toBe(0);
});

it('rejects invalid tenant contracts and missing configured relations', function () {
    migrateOwnershipForms(true);
    $user = FormOwnershipUser::create();
    config()->set('form-stepper.tenant.relationship', 'missingTenant');
    expect(fn () => $this->builder->tenant(ownershipRequest($user)))->toThrow(InvalidArgumentException::class);
    expect(fn () => app(FormService::class)->create($this->builder, [], $user, $user))
        ->toThrow(InvalidArgumentException::class);
    expect(fn () => app(FormService::class)->create($this->builder, [], null, FormOwnershipTenant::create()))
        ->toThrow(InvalidArgumentException::class);
});

it('creates personal forms for authenticated users without a current tenant', function () {
    migrateOwnershipForms(true);
    $user = FormOwnershipUser::create();
    $this->actingAs($user);
    $created = $this->postJson('/api/forms', ['type' => 'ownership'])->assertCreated()->json();
    $form = Form::query()->where('uuid', $created['id'])->firstOrFail();

    expect($form->requester->is($user))->toBeTrue()
        ->and($form->tenant)->toBeNull();
    $this->getJson('/api/forms?type=ownership')->assertOk()->assertJsonCount(1, 'data');
});

it('ignores current tenant resolution when the feature is disabled', function () {
    migrateOwnershipForms(false);
    config()->set('form-stepper.tenant.relationship', 'missingTenant');
    $user = FormOwnershipUser::create();
    expect($this->builder->tenant(ownershipRequest($user)))->toBeNull();

    $this->actingAs($user);
    $this->postJson('/api/forms', ['type' => 'ownership'])->assertCreated();
    $this->getJson('/api/forms?type=ownership')->assertOk()->assertJsonCount(1, 'data');
});

it('does not expose retained tenant forms after tenant support is disabled', function () {
    migrateOwnershipForms(true);
    $user = FormOwnershipUser::create();
    $tenant = FormOwnershipTenant::create();
    $service = app(FormService::class);
    $service->create($this->builder, [], $user, $tenant);
    $personal = $service->create($this->builder, [], $user, null)['form'];
    config()->set('form-stepper.tenant.enabled', false);

    expect($this->builder->scopeForms(Form::query(), ownershipRequest($user))->pluck('id')->all())
        ->toBe([$personal->id]);
});
