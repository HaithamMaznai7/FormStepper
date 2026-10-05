<?php

declare(strict_types=1);

namespace FormStepper\FormStepper\Forms;

use FormStepper\FormStepper\Models\Form;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
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
        return (string) config('form-stepper.default_mode', 'single');
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
        $user = $request->user();

        return $user instanceof Model ? $user : null;
    }

    public function creator(Request $request): ?Model
    {
        $user = $request->user();

        return $user instanceof Model ? $user : null;
    }

    public function tenant(Request $request): ?Model
    {
        return null;
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
                return;
            }

            abort(403);
        }

        if ($form !== null && $user instanceof Model) {
            if ($form->requester?->is($user) || $form->creator?->is($user)) {
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
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (EloquentBuilder $query) use ($user): void {
            $query->whereMorphedTo('requester', $user)
                ->orWhereMorphedTo('creator', $user);
        });
    }

    public function authorizeGuestResume(Request $request, Form $form): void
    {
        $token = $request->header('X-Form-Resume-Token');

        if (
            ! is_string($token) ||
            $form->resume_token_hash === null ||
            ! password_verify($token, $form->resume_token_hash)
        ) {
            abort(403);
        }
    }
}
