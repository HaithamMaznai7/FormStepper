<?php

declare(strict_types=1);

namespace FormStepper\FormStepper\Forms;

use FormStepper\FormStepper\Models\Form;
use FormStepper\FormStepper\Support\FormOwnership;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

abstract class FormBuilder
{
    abstract public function formType(): string;

    /**
     * @return list<array<string, mixed>>
     */
    public function steps(): array
    {
        return [];
    }

    public function mode(): string
    {
        return (string) config('form-stepper.default_mode', 'stepper');
    }

    public function resolveMode(?string $requestedMode): string
    {
        if ($requestedMode === null) {
            return $this->mode();
        }

        if (! config('form-stepper.allow_instance_mode_override', false)) {
            throw new InvalidArgumentException('This application does not allow per-form mode overrides.');
        }

        if (! in_array($requestedMode, ['single', 'stepper'], true)) {
            throw new InvalidArgumentException("Unsupported form mode [{$requestedMode}].");
        }

        return $requestedMode;
    }

    /**
     * Resolve the selected option keys to application-owned option models.
     *
     * @param  list<string>  $keys
     * @return list<mixed>
     */
    public function resolveOptions(array $keys): array
    {
        if ($keys !== []) {
            throw new InvalidArgumentException('The form builder must resolve its selected options.');
        }

        return [];
    }

    public function requester(Request $request): ?Model
    {
        return FormOwnership::requester($request->user());
    }

    public function tenant(Request $request): ?Model
    {
        $user = $this->requester($request);

        if (! FormOwnership::tenantEnabled() || $user === null) {
            return null;
        }

        $relationship = config('form-stepper.tenant.relationship', 'currentTenant');

        if (! is_string($relationship) || $relationship === '' || ! $user->isRelation($relationship)) {
            throw new InvalidArgumentException('Configure a valid current tenant relationship on the requester model.');
        }

        if (! $user->{$relationship}() instanceof Relation) {
            throw new InvalidArgumentException('The current tenant method must return an Eloquent relationship.');
        }

        $tenant = $user->getRelationValue($relationship);

        if ($tenant !== null && ! $tenant instanceof Model) {
            throw new InvalidArgumentException('The current tenant relationship must resolve to one model or null.');
        }

        FormOwnership::validate($user, $tenant);

        return $tenant;
    }

    /**
     * Return values keyed by step key for the resolved requester.
     *
     * @return array<string, array<string, mixed>>
     */
    public function prefillValues(?Model $requester): array
    {
        return [];
    }

    public function authorize(string $ability, Request $request, ?Form $form = null): void
    {
        $user = $request->user();

        if ($ability === 'create') {
            if ($user === null && config('form-stepper.allow_guest', true)) {
                return;
            }

            if ($user instanceof Model && $this->requester($request)?->is($user)) {
                $this->tenant($request);

                return;
            }

            abort(403);
        }

        if ($form !== null && $user instanceof Model) {
            if ($this->scopeForms(Form::query()->whereKey($form->getKey()), $request)->exists()) {
                return;
            }
        }

        abort(403);
    }

    /**
     * @param  EloquentBuilder<Form>  $query
     * @return EloquentBuilder<Form>
     */
    public function scopeForms(EloquentBuilder $query, Request $request): EloquentBuilder
    {
        $user = $request->user();

        if (! $user instanceof Model) {
            $query->whereNull('requester_type')->whereNull('requester_id');

            if (FormOwnership::tenantEnabled() || Schema::hasColumn($query->getModel()->getTable(), 'tenant_type')) {
                $query->whereNull('tenant_type')->whereNull('tenant_id');
            }

            $token = $request->header('X-Form-Resume-Token');

            if (! is_string($token) || $token === '') {
                abort(403);
            }

            $id = null;

            foreach ((clone $query)->whereNotNull('resume_token_hash')->cursor() as $form) {
                if (password_verify($token, $form->resume_token_hash)) {
                    $id = $form->getKey();

                    break;
                }
            }

            if ($id === null) {
                abort(403);
            }

            return $query->whereKey($id);
        }

        $query->whereMorphedTo('requester', $user);

        if (FormOwnership::tenantEnabled() || Schema::hasColumn($query->getModel()->getTable(), 'tenant_type')) {
            $tenant = $this->tenant($request);

            if ($tenant === null) {
                $query->whereNull('tenant_type')->whereNull('tenant_id');
            } else {
                $query->whereMorphedTo('tenant', $tenant);
            }
        }

        return $query;
    }

    public function authorizeGuestResume(Request $request, Form $form): void
    {
        $token = $request->header('X-Form-Resume-Token');

        if (
            $form->getAttribute('requester_type') !== null ||
            $form->getAttribute('requester_id') !== null ||
            $form->getAttribute('tenant_type') !== null ||
            $form->getAttribute('tenant_id') !== null ||
            ! is_string($token) ||
            $form->resume_token_hash === null ||
            ! password_verify($token, $form->resume_token_hash)
        ) {
            abort(403);
        }
    }
}
