---
name: form-stepper-development
description: >
  Configure and apply the Form Stepper package in Laravel applications.
license: MIT
metadata:
  author: Haitham Maznai
---

# Form Stepper

Use this skill when a Laravel application needs to integrate the Form Stepper package.

## Primary Goal

- Configure application-owned form builders and option models, then use the package API to create,
  resume, and complete durable forms.

## Workflow

### 1. Install and configure

- Install `haitham-maznai/form-stepper` with Composer. Until published on Packagist, add the VCS
  repository `https://github.com/HaithamMaznai7/FormStepper.git` and require `dev-main`.
- Requires PHP 8.3+ and Laravel 10.48.29+/11/12/13. Prefer maintained Laravel releases; legacy
  compatibility does not guarantee security support.
- Install `doctrine/dbal:^3.9` before the ownership upgrade on Laravel 10 with SQLite.
- Publish the configuration and migrations with `php artisan vendor:publish --tag="form-stepper-config"` and
  `php artisan vendor:publish --tag="form-stepper-migrations"`.
- Set table names and route settings before running `php artisan migrate`.
- Optionally publish `form-stepper-routes` and `form-stepper-controller`. The provider prefers
  `routes/form-stepper.php` over bundled routes; do not manually include it again.
- Set `routes.controller` to `App\Http\Controllers\FormStepperController::class` to use the
  published subclass. It inherits the package controller's actions and access checks.
- `routes.enabled: false` disables automatic loading of both bundled and published routes.

### 2. Register each workflow builder

- Extend `HaithamMaznai\FormStepper\Forms\FormBuilder` for each form type.
- Implement `formType()` and optionally override `mode()`, `steps()`, `resolveOptions()`, and the
  requester, tenant, authorization, and listing-scope methods. Creator ownership is not supported.
- Register each builder class under the `builders` config key.

### 3. Provide option schemas and scope context

- Make application option models implement `ProvidesFormRequirements`. Return stable option keys,
  requirement steps, required option keys, compatible option keys, and excluded option keys.
- Create the option catalog and optional input lookup tables in the application; the package's
  `form_options` table stores selected references, not catalog definitions.
- Build snapshots with `Schema\Input::make()` / `fromArray()`, `Schema\Step::make()`, and
  `Schema\Requirements::make(...$steps)`. Store `toArray()` in array-cast JSON columns, not
  `toJson()` (which would double-encode). Return `toArray()` from `formSteps()` and builder `steps()`.
- Lookup edits do not mutate saved option snapshots. Rebuild options explicitly, then recompute
  selected options on drafts that should adopt the new definitions.
- Use matching step/input keys and compatible metadata for merging. Single mode keeps the same
  step-grouped schema and values; it changes submission/UI navigation, not option storage.
- Make requester and tenant models implement `ProvidesAvailableTypes` when their available form
  types should restrict the schema.
- Enable `tenant.enabled` before migrating to create tenant morph columns. Configure
  `tenant.relationship` (default `currentTenant`) on the authenticated user. Its related model must
  implement `Contracts\TenantForm` with a `forms(): MorphMany` tenant relationship.
- Default access is requester + current tenant context, or personal forms when no tenant is
  selected. Tenant membership must be enforced by the application. Guest identities are null.
- Use stable step and input keys. Configure scope lists with form-type keys; leave a scope null or
  omit it to allow every type.
- Correct existing `tenant-types` model class names and duplicate JSON keys before using the
  compatibility adapter.

### 4. Connect the UI to the JSON API

- Create a draft with `POST /api/forms`, read or resume it with `GET /api/forms/{uuid}`, save
  stepper answers with `PUT /api/forms/{uuid}/steps/{step}`, and finish using the review endpoint
  `POST /api/forms/{uuid}/complete`.
- Use `POST /api/forms/{uuid}/submit` for single-mode forms. Single-mode responses expose groups as
  `definition.containers` and omit `current_step_id` and `requires_authentication`. Use
  `PATCH /api/forms/{uuid}/options` when selected options change.
- Store the guest `resume_token` securely and return it in `X-Form-Resume-Token` when resuming a
  guest draft. A logged-in user can claim that draft through the same token.
- Guest listing also requires this token and returns only its matching form. Claiming associates
  the current tenant when enabled and clears the guest token.
- Read `current_step_id` as a string step key and `status: submitted` after completion.
- Each step/container and requirement in the response carries `rules` and saved `values`/`value`
  (`null` before saving); repeatable steps add `repeats`. Replace any level via
  `config('form-stepper.resources')` with a class extending the package resource.
- Back up existing installations before publishing only the new ownership upgrade migration:
  it deletes creator data, renames the old step field, and converts completed statuses. It cannot
  be rolled back without restoring a backup.
- When claiming a draft, the builder's `prefillValues()` hook fills missing requester fields while
  preserving values the guest already entered.
- Mark authentication-gated steps with `requires-authentication: true`; guest drafts remain saved
  but cannot be submitted until claimed by an authenticated requester.
- Render the returned arrayable schema with the host app's chosen UI; option catalogs and any
  dynamic choice endpoints remain application-owned.

### 5. Optional admin panel and lookup library

- Publish and run the `create_form_library_tables` migration (input types with seeded system
  types, lookup inputs with complex children, library steps with ordered inputs).
- Set `form-stepper.admin.enabled` to `true` and define the `manage-form-stepper` Gate; routes use
  `['web', 'auth']` under `form-stepper/admin`.
- Snapshot library records into options with `FormStepTemplate::toStep()` and
  `FormInput::toInput()` wrapped in `Requirements::make(...)->toArray()`; library edits do not
  change saved options until rebuilt.
- Customize with `vendor:publish --tag=form-stepper-views` and
  `--tag=form-stepper-admin-controllers`, then point `form-stepper.admin.controllers` at the
  published classes.
- Admin edits of any draft step go through `FormService::updateStepValues()`.

## References

- `src/Forms/FormBuilder.php`
- `src/Contracts/ProvidesFormRequirements.php`
- `src/Contracts/ProvidesAvailableTypes.php`
- `src/Schema/Input.php`
- `src/Schema/Step.php`
- `src/Schema/Requirements.php`
- `config/form-stepper.php`
- `routes/form-stepper.php`
- `routes/form-stepper-admin.php`
- `src/Models/FormInput.php`
- `src/Models/FormStepTemplate.php`
- `README.md`

## Examples

```php
final class OrderFormBuilder extends \HaithamMaznai\FormStepper\Forms\FormBuilder
{
    public function formType(): string
    {
        return 'order';
    }

    public function mode(): string
    {
        return 'stepper';
    }
}
```

## Anti-patterns

- Do not trust a client-provided form type, option key, step key, or scope; the package resolves and
  validates these against server-side builders.
- Do not expose guest resume tokens in logs or URLs.
- Do not delete draft forms when a user closes the application.
- Do not treat a model class name as a form-type scope value.
