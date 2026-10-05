<div align="center">
    <h1>Form Stepper</h1>
</div>

<p align="center">
    <a href="https://packagist.org/packages/haitham-maznai/form-stepper"><img src="https://img.shields.io/packagist/v/haitham-maznai/form-stepper.svg?style=flat-square" alt="Packagist"></a>
    <a href="https://packagist.org/packages/haitham-maznai/form-stepper"><img src="https://img.shields.io/packagist/php-v/haitham-maznai/form-stepper.svg?style=flat-square" alt="PHP from Packagist"></a>
    <a href="https://packagist.org/packages/haitham-maznai/form-stepper"><img src="https://badge.laravel.cloud/badge/haitham-maznai/form-stepper?style=flat" alt="Laravel versions"></a>
    <a href="https://github.com/haitham-maznai/form-stepper/actions"><img alt="GitHub Workflow Status (main)" src="https://img.shields.io/github/actions/workflow/status/haitham-maznai/form-stepper/tests.yml?branch=main&label=Tests&style=flat-square"></a>
    <a href="https://packagist.org/packages/haitham-maznai/form-stepper"><img src="https://img.shields.io/packagist/dt/haitham-maznai/form-stepper.svg?style=flat-square" alt="Total Downloads"></a>
</p>

Persistent, dynamic single-step and stepper forms for Laravel.

## Installation

You can install the package via Composer:

```bash
composer require haitham-maznai/form-stepper
```

You may publish all of the package's resources at once:

```bash
php artisan vendor:publish --tag="form-stepper"
```

Or, you may publish each resource individually:

### Publishing the Configuration File

```bash
php artisan vendor:publish --tag="form-stepper-config"
```

### Publishing and Running the Migrations

```bash
php artisan vendor:publish --tag="form-stepper-migrations"
php artisan migrate
```

The dynamic form engine uses the configured `tables.forms`, `tables.options`, and `tables.steps`
tables, with polymorphic requester, creator, and tenant identities. The older `form_items`
migration is retained and references `tables.forms`. If you have already run the earlier forms
migration, plan a separate data/schema migration before using the engine; publishing this package
does not upgrade existing tables automatically.

### Publishing the Views

```bash
php artisan vendor:publish --tag="form-stepper-views"
```

### Publishing the Translations

```bash
php artisan vendor:publish --tag="form-stepper-lang"
```

### Publishing the Public Assets

```bash
php artisan vendor:publish --tag="form-stepper-assets"
```

## Usage

Register one builder per form type in `config/form-stepper.php`. A builder owns the workflow's
base steps and resolves selected option keys to application models implementing
`ProvidesFormRequirements`:

```php
use FormStepper\FormStepper\Forms\FormBuilder;
use App\Models\FormOption;

final class OrderFormBuilder extends FormBuilder
{
    public function formType(): string
    {
        return 'order';
    }

    public function mode(): string
    {
        return 'stepper';
    }

    public function resolveOptions(array $keys): array
    {
        return FormOption::query()
            ->whereIn('key', $keys)
            ->get()
            ->all();
    }
}
```

```php
'builders' => [
    'order' => App\Forms\OrderFormBuilder::class,
],
```

Option models provide `formOptionKey()`, `formSteps()`, `requires()`, `compatibleWith()`, and
`excludes()`. Requester and tenant models that restrict available form types implement
`ProvidesAvailableTypes::getAvailableTypes()`. The package returns the resolved form definition as
an arrayable API payload; the host application can render it with its preferred JavaScript,
Blade, or Livewire UI.

The schema supports `input`, `selection`, `single-selection`, `multiple-selection`, `radio`,
`checkbox`, `boolean`, `plate`, and nested `complex` inputs. Validation rules are Laravel rules.
Repeatable steps store a list of keyed instances under `repeat-name`. The scope fields
`types-scope`, `tenant-types`, and `requester-scope` compare form-type keys; `guest-scope` accepts
`guest` or `authenticated`. Null and omitted scopes are unrestricted. In the legacy schema,
`business-types` is read as `requester-scope`; old class-name values in `tenant-types` and duplicate
JSON property names must be corrected manually because they cannot be mapped safely.
Mark a step with `requires-authentication: true` to block guest completion, even when that step is
hidden from guest schemas by scope. Responses indicate the remaining authentication requirement.

The package routes are enabled by default under `/api/forms`. They can be configured or disabled
with the `routes` settings. A guest form response includes a one-time `resume_token`; send it in
the `X-Form-Resume-Token` header when reading or updating that draft. Authenticated applications
can override `FormBuilder::requester()`, `creator()`, `tenant()`, `authorize()`, and `scopeForms()`
for their identity and tenant rules. When a tenant administrator creates a form for another
requester, resolve that requester and enforce the tenant policy in the custom builder; the default
builder only allows a user to create their own form. Override `prefillValues()` to seed draft
values from the resolved requester, including when a guest draft is claimed after login; only
missing fields are filled on claim, and fields already entered by the guest are preserved.

The API supports:

- `POST /api/forms` to create a draft (`type` and selected `options`).
- `GET /api/forms/{uuid}` and `GET /api/forms?type=...` to read/resume or list forms.
- `PATCH /api/forms/{uuid}/options` to change selections and recompute the form schema while
  retaining values for steps and inputs that still exist.
- `PUT /api/forms/{uuid}/steps/{step}` to validate and save a step, and
  `POST /api/forms/{uuid}/complete` to finish from the review step.
- `POST /api/forms/{uuid}/submit` for a single-mode form.

Table names can be changed in the package configuration before running the migrations.
Per-form mode overrides are disabled by default; enable `allow_instance_mode_override` to accept
`mode: single` or `mode: stepper` when creating a form.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Thank you for considering contributing to Form Stepper! Please review our [contributing guide](.github/CONTRIBUTING.md) to get started.

## Security Vulnerabilities

Please review [our security policy](.github/SECURITY.md) on how to report security vulnerabilities.

## Credits

- [Haitham Maznai](https://github.com/haitham-maznai)
- [All Contributors](../../contributors)

## License

Form Stepper is open-sourced software licensed under the [MIT license](LICENSE.md).
