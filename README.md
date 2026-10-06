# Form Stepper

[![Tests](https://github.com/HaithamMaznai7/FormStepper/actions/workflows/tests.yml/badge.svg)](https://github.com/HaithamMaznai7/FormStepper/actions/workflows/tests.yml)

An API-first Laravel package for persistent, dynamic forms. Build a single form or a stepper,
combine application-owned options into a validated schema, save drafts, and resume them later.
Forms belong to a requester, with optional current-tenant ownership. Guests can start forms and
claim them after login.

**This package is the backend engine, not a ready-made form UI.** Your application renders the
returned schema using Blade, Livewire, Vue, React, or another frontend. It also owns authentication,
tenant membership, option catalogs, and any business action performed after submission.

Repository: [HaithamMaznai7/FormStepper](https://github.com/HaithamMaznai7/FormStepper).
Composer name: `haithammaznai7/form-stepper`. PHP namespace: `FormStepper\FormStepper`.
There is no tagged package release documented here yet; the instructions below install `dev-main`.

## Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Quick start: a working form](#quick-start-a-working-form)
- [Configuration](#configuration)
- [Dynamic options](#dynamic-options)
- [Input schemas and validation](#input-schemas-and-validation)
- [Scopes and authentication steps](#scopes-and-authentication-steps)
- [Requester and tenant ownership](#requester-and-tenant-ownership)
- [JSON API](#json-api)
- [Using the package on a website](#using-the-package-on-a-website)
- [Direct service usage](#direct-service-usage)
- [Upgrading existing installations](#upgrading-existing-installations)
- [Development and troubleshooting](#development-and-troubleshooting)

## Requirements

- PHP **8.3 or later** within PHP 8.x.
- Laravel **12 or 13**. Laravel 11 is not supported by the current Composer constraints.
- A database supported by Laravel; configure it before running migrations.

The package uses Laravel's database, HTTP, routing, support, and validation components.
Its development suite uses Orchestra Testbench 10/11 and Pest 4/5.

## Installation

### Install directly from GitHub

From your Laravel application's directory:

```bash
composer config repositories.form-stepper vcs https://github.com/HaithamMaznai7/FormStepper.git
composer require haithammaznai7/form-stepper:dev-main
```

No Packagist registration is assumed. Once the package is registered there and stable releases
are tagged, you can install the appropriate released constraint instead of `dev-main`.
For a private GitHub repository, configure Composer's GitHub authentication outside source control.

### Install from a local checkout

Add a path repository to your application's `composer.json` alongside any existing repositories:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "C:\\Users\\haith\\Herd\\form-stepper",
            "options": {
                "symlink": true,
                "versions": {
                    "haithammaznai7/form-stepper": "dev-main"
                }
            }
        }
    ]
}
```

Then run `composer require haithammaznai7/form-stepper:dev-main`. Replace the example path with
your checkout. Composer discovers the package service provider automatically.

The former name was `haitham-maznai/form-stepper`. Existing applications must replace that
requirement and update their repository configuration to use the new name; the PHP namespace and
publish tags have not changed.

### Publish configuration and migrations

For a **new installation**:

```bash
php artisan vendor:publish --tag=form-stepper-config
php artisan vendor:publish --tag=form-stepper-migrations
```

Edit `config/form-stepper.php` before migrating: choose your table names, tenant support, builders,
and route middleware. Then run:

```bash
php artisan migrate
php artisan route:list --name=form-stepper.forms
```

The active initial migration creates `forms`, `form_options`, and `form_steps`, or your configured
names. The preserved `form_items` migration is currently disabled. The ownership upgrade migration
also ships with the package and is safe to run against the current initial schema, but its `down()`
is intentionally blocked. Read [upgrade instructions](#upgrading-existing-installations) before
upgrading an existing installation or planning migration rollbacks.

Additional publish tags are `form-stepper`, `form-stepper-views`, `form-stepper-lang`, and
`form-stepper-assets`. These include starter resources, not a complete form renderer.

## Quick start: a working form

Create `app/Forms/ContactFormBuilder.php` in your application:

```php
<?php

namespace App\Forms;

use FormStepper\FormStepper\Forms\FormBuilder;

class ContactFormBuilder extends FormBuilder
{
    public function formType(): string
    {
        return 'contact';
    }

    public function mode(): string
    {
        return 'stepper';
    }

    public function steps(): array
    {
        return [
            [
                'key' => 'contact-details',
                'title' => 'Contact information',
                'requirements' => [
                    [
                        'key' => 'name',
                        'type' => 'input',
                        'label' => 'Name',
                        'rules' => ['required', 'string', 'max:100'],
                    ],
                    [
                        'key' => 'email',
                        'type' => 'input',
                        'label' => 'Email',
                        'rules' => ['required', 'email'],
                    ],
                ],
            ],
        ];
    }
}
```

Register it in `config/form-stepper.php`:

```php
'builders' => [
    'contact' => App\Forms\ContactFormBuilder::class,
],
```

For guest testing, keep `allow_guest` enabled. The following JSON requests work with Postman,
an HTTP client, or your frontend. Send `Accept: application/json` and, for JSON bodies,
`Content-Type: application/json`.

**1. Create a draft:** `POST /api/forms`

```json
{"type":"contact","options":[]}
```

Expect HTTP 201 with a UUID in `id`, `status: "draft"`, `current_step_id: "contact-details"`,
the schema in `definition`, and a one-time guest `resume_token`. Keep the UUID and token securely.

**2. Save the step:** `PUT /api/forms/{uuid}/steps/contact-details`

Send `X-Form-Resume-Token: <returned token>` for a guest:

```json
{"values":{"name":"Ada","email":"ada@example.test"}}
```

Expect HTTP 200 and `current_step_id: "review"`. Invalid values produce HTTP 422 without advancing.

**3. Resume or review:** `GET /api/forms/{uuid}`, using the same guest header.
Saved answers are in `values["contact-details"]`. The returned definition includes a computed
review step; render it from the earlier steps and their saved values.

**4. Submit:** `POST /api/forms/{uuid}/complete`, using the same guest header.
Expect `status: "submitted"` and `completed_at`. Submission does not automatically create an order,
send an email, or execute an application-specific action.

For a single form, return `'single'` from `mode()` and submit all step groups with
`POST /api/forms/{uuid}/submit`:

```json
{"values":{"contact-details":{"name":"Ada","email":"ada@example.test"}}}
```

## Configuration

Core settings in `config/form-stepper.php`:

| Setting | Default | Purpose |
|---|---|---|
| `default_mode` | `stepper` | Mode when a builder does not override `mode()`. |
| `allow_guest` | `true` | Default builder allows unauthenticated creation. |
| `allow_instance_mode_override` | `false` | Accept `mode: single` or `mode: stepper` on creation. |
| `builders` | `[]` | Map form types to application builder classes. |
| `tenant.enabled` | `false` | Enable current-tenant resolution and tenant columns on fresh installs. |
| `tenant.relationship` | `currentTenant` | Eloquent relationship on the authenticated user. |
| `tables.forms` | `forms` | Main form records. |
| `tables.options` | `form_options` | Selected option references and keys. |
| `tables.steps` | `form_steps` | Saved values grouped by step key. |
| `routes.enabled` | `true` | Load the package JSON routes. |
| `routes.prefix` | `api/forms` | URL prefix. |
| `routes.middleware` | `['api']` | Choose your API authentication or website session middleware. |
| `routes.name` | `form-stepper.forms.` | Route name prefix. |

The retained starter keys `enabled`, `placeholder`, `types`, `default_type`, `default_step`,
`last_step`, and `saleables` are not the engine's workflow configuration. Use `builders`,
`default_mode`, the schema, and `routes.enabled` for the engine. The computed last step is `review`.

Configure table names before migrating. Changing names later does not rename existing tables.
After changing configuration in an application with cached config, rebuild its config cache.

## Dynamic options

Options are **application-owned Eloquent models** implementing `ProvidesFormRequirements`.
The package stores their morph references and stable keys; it does not create your option catalog.

For example, add this implementation to an application `FormOption` model whose table contains
`key`, `requirements`, `requires`, `compatible_with`, and `excludes` columns:

```php
namespace App\Models;

use FormStepper\FormStepper\Contracts\ProvidesFormRequirements;
use Illuminate\Database\Eloquent\Model;

class FormOption extends Model implements ProvidesFormRequirements
{
    protected $fillable = ['key', 'requirements', 'requires', 'compatible_with', 'excludes'];

    protected function casts(): array
    {
        return [
            'requirements' => 'array',
            'requires' => 'array',
            'compatible_with' => 'array',
            'excludes' => 'array',
        ];
    }

    public function formOptionKey(): string { return $this->key; }
    public function formSteps(): array { return $this->requirements ?? []; }
    public function requires(): array { return $this->requires ?? []; }
    public function compatibleWith(): array { return $this->compatible_with ?? []; }
    public function excludes(): array { return $this->excludes ?? []; }
}
```

These JSON columns are an example storage design, not package-migrated tables. `formSteps()` returns
the same step structure as the builder's `steps()`. You can return hardcoded schemas instead.

### Create the option catalog table

The package's `form_options` table stores selections **on a form**, not the catalog of options.
Create a separate table in your application; for example run
`php artisan make:migration create_application_form_options_table`, then use:

```php
public function up(): void
{
    \Illuminate\Support\Facades\Schema::create('application_form_options', function (
        \Illuminate\Database\Schema\Blueprint $table,
    ): void {
        $table->id();
        $table->string('key')->unique();
        $table->json('requirements');
        $table->json('requires')->nullable();
        $table->json('compatible_with')->nullable();
        $table->json('excludes')->nullable();
        $table->timestamps();
    });
}

public function down(): void
{
    \Illuminate\Support\Facades\Schema::dropIfExists('application_form_options');
}
```

Add `protected $table = 'application_form_options';` to the `App\Models\FormOption` example above.
Run `php artisan migrate` in your application. Use your own naming and tenant/access columns if
needed; do not reuse the package's selected-options table for this catalog.

### Build arrayable inputs, steps, and requirements

The package provides immutable snapshot authoring classes:

```php
use FormStepper\FormStepper\Schema\Input;
use FormStepper\FormStepper\Schema\Step;
use FormStepper\FormStepper\Schema\Requirements;

$name = Input::make('name', attributes: [
    'label' => 'Name',
    'rules' => ['required', 'string'],
]);

$inspectionRequirements = Requirements::make(
    Step::make('contact', [
        $name,
        Input::make('email', attributes: ['rules' => ['required', 'email']]),
    ], ['title' => 'Contact']),
);

$deliveryRequirements = Requirements::make(
    Step::make('contact', [
        $name,
        Input::make('phone', attributes: ['rules' => ['required', 'string']]),
    ], ['title' => 'Contact']),
);
```

Each class implements Laravel `Arrayable`, `Jsonable`, and PHP `JsonSerializable`.
The APIs are:

| Method | Purpose |
|---|---|
| `Input::make($key, $type = 'input', $attributes = [])` | Create an input; attributes contain rules, labels, scopes, or metadata. |
| `Input::complex($key, $children, $attributes = [])` | Create a complex input from a list of `Input` instances. |
| `Input::fromArray($definition)` | Load a complete raw input definition from a lookup record. |
| `Step::make($key, $inputs = [], $attributes = [])` | Create a step from a list of `Input` instances. |
| `Step::fromArray($definition)` | Load a complete raw step definition. |
| `Requirements::make(...$steps)` | Build an option's ordered list of `Step` instances. |
| `Requirements::fromArray($steps)` | Load the list from an Eloquent array-cast column. |
| `Requirements::fromJson($json)` | Load a JSON string whose root is a list of steps. |
| `toArray()` / `toJson()` | Export the raw schema snapshot. |

Constructors are not public; use the factories. Attributes use the existing schema names:
`requires-authentication`, `repeatable`, `repeat-name`, and scope fields. Factories validate through
the same normalizer used by forms, reject duplicate input keys within a step and duplicate step
keys within one option, and preserve the original schema attributes rather than saving the
runtime-normalized schema. Errors are explicit: invalid schema/non-JSON data raises
`InvalidArgumentException`; JSON encoding/decoding failures raise `JsonException`.

Snapshot values must be arrays, scalars, or null. Executable rules, closures, objects, and resources
cannot be stored through these classes; use serializable rule strings. `Input` and `Step`
factories do not infer rules from type names or labels.

### Save option records and fetch their definitions

Using the option model and catalog table above:

```php
use App\Models\FormOption;

$inspection = FormOption::updateOrCreate(
    ['key' => 'inspection'],
    [
        'requirements' => $inspectionRequirements->toArray(),
        'requires' => [],
        'compatible_with' => ['delivery'],
        'excludes' => [],
    ],
);

$delivery = FormOption::updateOrCreate(
    ['key' => 'delivery'],
    [
        'requirements' => $deliveryRequirements->toArray(),
        'requires' => [],
        'compatible_with' => ['inspection'],
        'excludes' => [],
    ],
);
```

**For Eloquent columns cast as `array`, assign `toArray()`, not `toJson()`.** Eloquent encodes
the array into JSON. Assigning an already-encoded string would double-encode it.
Use `toJson()` for an explicit JSON export or an uncast string column instead.

Optionally validate stored snapshots when implementing the contract:

```php
public function formSteps(): array
{
    return \FormStepper\FormStepper\Schema\Requirements::fromArray(
        $this->requirements ?? [],
    )->toArray();
}
```

The engine calls this method on the models returned by `resolveOptions()` below; no package
option-creation endpoint or option catalog model is needed. Seeders, admin pages, and authorized
controllers that create catalog records belong to your application.

### Reuse inputs from an application lookup table

For an admin-managed input library, create an application table such as `form_input_definitions`
with `id`, unique `key`, JSON `definition`, and timestamps. Its model, e.g. `InputDefinition`, casts
`definition` to `array` and permits `key`/`definition` assignment through `$fillable`.
An option editor can select these records and compose steps:

```php
use App\Models\InputDefinition;
use FormStepper\FormStepper\Schema\Input;
use FormStepper\FormStepper\Schema\Step;
use FormStepper\FormStepper\Schema\Requirements;

$lookup = InputDefinition::updateOrCreate(
    ['key' => 'name'],
    ['definition' => Input::make('name', attributes: [
        'label' => 'Name',
        'rules' => ['required', 'string'],
    ])->toArray()],
);

$selectedInput = Input::fromArray($lookup->definition);
$requirements = Requirements::make(
    Step::make('contact', [$selectedInput], ['title' => 'Contact']),
);

$inspection->update(['requirements' => $requirements->toArray()]);
```

This stores the **entire input definition**, not its lookup ID. Editing/deleting the lookup later
does not mutate the saved option snapshot. Explicitly rebuild and save an option to apply updated
lookup definitions. Existing forms also keep their own resolved schema snapshot; updating an
option does not automatically rewrite all saved drafts. Recomputing a draft's selected options
loads current option snapshots and retains only compatible values.

You may use application-owned pivot tables to remember which lookup records an editor selected,
but the runtime snapshot remains self-contained. Reuse stable keys and consistent labels/types
when the same input should deduplicate across options.

### Resolve selected options and merge them

Override your builder's `resolveOptions()`:

```php
public function resolveOptions(array $keys): array
{
    return \App\Models\FormOption::query()
        ->whereIn('key', $keys)
        ->get()
        ->all();
}
```

Restrict this query to options the user/context is allowed to use. Each requested key must resolve
exactly once. Send selected keys as `"options": ["inspection", "delivery"]` when creating a draft.

- `requires()` lists option keys that must also be selected; they are not automatically added.
- `excludes()` lists mutually exclusive keys.
- An empty `compatibleWith()` means no allow-list restriction. A non-empty list must include every
  other selected option.
- Matching step/input keys are merged when their definitions are compatible. Incompatible
  definitions are rejected rather than silently overridden.
- `PATCH /api/forms/{uuid}/options` with `{"options":["inspection"]}` recomputes a draft.
  Compatible saved values are retained; removed steps/inputs and changed input types lose their
  values. The engine chooses the next incomplete/invalid step.

Your frontend must render the option selector and disable conflicting choices for convenience;
the package enforces compatibility again on the server.

With the two saved options above, creating a form with
`{"type":"contact","options":["inspection","delivery"]}` produces **one** `contact` group with
`name`, `email`, and `phone`: the shared `name` definition appears once. The builder's own steps
are merged first, followed by selected option steps. Matching keys must have compatible metadata;
compatible rules are combined rather than discarded. This is key-based merging within a step,
not global deduplication across differently named steps.

The same snapshots work for both modes:

- **Stepper:** save `{"values":{"name":"Ada","email":"ada@example.test","phone":"123"}}` at
  `PUT /api/forms/{uuid}/steps/contact`, then submit review.
- **Single:** return `'single'` from the builder's `mode()`. Render all input groups together and
  submit `{"values":{"contact":{"name":"Ada","email":"ada@example.test","phone":"123"}}}` at
  `POST /api/forms/{uuid}/submit`. Step keys still namespace saved values even though there is
  no step-by-step UI.

For fixed base requirements, your builder can also return
`Requirements::make(Step::make('contact', [$name]))->toArray()` from `steps()`.
The public option/builder contracts continue returning arrays; call `toArray()` at this boundary
instead of returning the definition objects directly.

## Input schemas and validation

Supported `type` values:

| Type | Suggested rendering |
|---|---|
| `input` | Text or another basic input chosen by your renderer. |
| `selection`, `single-selection` | One selected value. |
| `multiple-selection` | Multiple selected values. |
| `radio` | Radio group. |
| `checkbox`, `boolean` | Checkbox or boolean control. |
| `plate` | Application-specific plate input. |
| `complex` | Nested group of inputs. |

Each input needs a stable `key` and supported `type`. Optional metadata includes `label`,
`placeholder`, `value`, and `extra`. Choice rendering metadata can be carried in `extra`.
**Types and choice metadata do not enforce allowed values by themselves**: specify Laravel
validation `rules`, such as `['required', 'in:small,large']` or
`['required', 'array']` for multiple selections. JSON option schemas should use serializable
rule definitions, not executable closures.

Nested example:

```php
[
    'key' => 'address',
    'type' => 'complex',
    'requirements' => [
        ['key' => 'city', 'type' => 'input', 'rules' => ['required', 'string']],
        ['key' => 'postcode', 'type' => 'input', 'rules' => ['nullable', 'string']],
    ],
]
```

Send `{"values":{"address":{"city":"Riyadh","postcode":"12345"}}}` when saving that step.
Validation is server-side. Unknown steps/fields are rejected; complex values are checked against
their active child schema.

### Repeatable values

The current implementation repeats **steps**, not arbitrary standalone input definitions.
Put one or more fields in a repeatable step:

```php
[
    'key' => 'vehicles',
    'title' => 'Vehicles',
    'repeatable' => true,
    'repeat-name' => 'vehicles',
    'requirements' => [
        ['key' => 'plate', 'type' => 'input', 'rules' => ['required', 'string']],
    ],
]
```

Save with:

```json
{"values":{"vehicles":[{"plate":"ABC-123"},{"plate":"XYZ-456"}]}}
```

`repeatable-scope` can constrain when multiple instances are allowed; the resolved schema exposes
`can_repeat`. Render add/remove controls in the host UI. Duplicate input definitions do not create
duplicate values automatically.

## Scopes and authentication steps

Steps and inputs can use:

```php
'types-scope' => ['order'],
'tenant-types' => ['order'],
'requester-scope' => ['order'],
'guest-scope' => ['authenticated'],
```

- Null or omitted scopes are unrestricted (`['*']`); `[]` matches nothing.
- Values within a dimension are alternatives; different dimensions must all match.
- `types-scope`, `tenant-types`, and `requester-scope` contain **form-type keys**, not PHP class names.
- For tenant/requester scopes, models must implement
  `FormStepper\FormStepper\Contracts\ProvidesAvailableTypes` with
  `getAvailableTypes(): array`, returning allowed form-type keys. If that interface is implemented,
  the active type must be in its returned list even for an unrestricted dimension.
- A restricted tenant/requester scope does not match when the respective identity is null.
- `guest-scope` accepts `guest`, `authenticated`, or `*`.
- Legacy `business-types` maps to requester scope. Correct old tenant class-name values and
  duplicate JSON property names manually; they cannot be safely recovered by the normalizer.

Mark a step with `'requires-authentication' => true` to require login before saving it or
submitting a form with an applicable authentication-required step. This gate also applies when
that step is hidden by guest visibility. API responses expose `authentication_required` for the
form and `requires_authentication` for the current step.

Do not hide an authentication-required step behind a requester scope that cannot match guests
and expect that scope alone to act as an authentication gate. Design guest/authenticated scopes
and the explicit authentication flag together.

## Requester and tenant ownership

There is **no creator identity**.

| Context | Requester | Tenant |
|---|---|---|
| Guest | Null | Null |
| Authenticated without current tenant | Authenticated user | Null |
| Authenticated with current tenant and tenant support enabled | Authenticated user | Current tenant |

Requester morph columns always exist. For fresh installations, tenant morph columns exist only
when tenant support is enabled before migration. Morph IDs use strings to support string/UUID
model keys as well as integers.

Enable tenant support:

```php
'tenant' => [
    'enabled' => true,
    'relationship' => 'currentTenant',
],
```

The authenticated user must have that Eloquent relationship, returning one tenant or null.
A missing relationship or invalid tenant raises an explicit error. For example:

```php
public function currentTenant(): \Illuminate\Database\Eloquent\Relations\BelongsTo
{
    return $this->belongsTo(Team::class, 'current_tenant_id');
}
```

The tenant model must implement `TenantForm`:

```php
namespace App\Models;

use FormStepper\FormStepper\Contracts\TenantForm;
use FormStepper\FormStepper\Models\Form;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Team extends Model implements TenantForm
{
    /** @return MorphMany<Form, $this> */
    public function forms(): MorphMany
    {
        return $this->morphMany(Form::class, 'tenant');
    }
}
```

**Your application must ensure the current tenant belongs to the user.** The interface defines
the forms relationship, not your membership or permission system.

Default listing and access require both the authenticated requester and the current tenant
context. Personal forms are visible when no tenant is selected. Selecting another tenant does
not grant access to its other requesters' forms. Disabling tenant support does not expose retained
tenant forms as personal forms.

`$team->forms()` returns that tenant's full forms relationship. Filter it to a requester for user
forms; authorize explicitly before using it for tenant administrators. For an administrator creating
on behalf of another requester, override `requester()`, `authorize()`, and `scopeForms()` in an
application builder. Never trust client-provided requester/tenant IDs without a policy check.

### Guest continuation and login

- Only a hash of the guest resume token is stored. The token is returned once at creation.
- Send `X-Form-Resume-Token` when reading, saving, changing options, listing, or submitting as guest.
- Guest queries require null requester and tenant identities; listing returns only the token-matched
  form. Missing/invalid tokens receive HTTP 403.
- Keep the token outside URLs and logs. Use a secure application-managed store.
- After login, send the token with the next form request. The package claims the form for the
  resolved requester/current tenant, refreshes its schema, preserves compatible values, and clears
  the token hash. Later access uses authenticated ownership.
- Guest completion is permitted only when applicable steps do not require authentication.

To prefill requester information, override:

```php
public function prefillValues(?\Illuminate\Database\Eloquent\Model $requester): array
{
    return $requester === null ? [] : [
        'contact-details' => ['name' => $requester->getAttribute('name')],
    ];
}
```

Prefill keys must belong to the active schema. On claim, only missing values are filled; existing
guest answers are preserved.

## JSON API

Default prefix: `/api/forms`. All endpoints return JSON; send `Accept: application/json`.
Authenticated requests need the host application's chosen authentication middleware/guard.

| Method | Endpoint | Body/query |
|---|---|---|
| POST | `/api/forms` | `type`, optional `options`, optional enabled `mode` override. |
| GET | `/api/forms/{uuid}` | Read/resume a form. |
| GET | `/api/forms` | List drafts for required `type`; optional `per_page` (1-100), `page`. |
| PATCH | `/api/forms/{uuid}/options` | `{"options":["option-key"]}`; `[]` clears selections. |
| PUT | `/api/forms/{uuid}/steps/{step}` | `{"values":{...}}`; only the current step of a stepper. |
| POST | `/api/forms/{uuid}/submit` | `{"values":{"step-key":{...}}}`; single-mode submission. |
| POST | `/api/forms/{uuid}/complete` | Stepper submission from review. |

Creation returns HTTP 201; successful reads/updates return HTTP 200. Validation errors use 422,
unauthorized default access uses 403, and invalid lifecycle actions use 409. Invalid builder/schema
configuration raises an exception: fix the server-side definition rather than treating it as a
successful empty form.

Individual responses contain:

| Field | Meaning |
|---|---|
| `id` | Public form UUID, not the numeric database ID. |
| `type`, `mode`, `status` | Registered type, single/stepper mode, draft/submitted state. |
| `current_step_id` | String step key (or `review`); omitted when null. |
| `definition` | Effective schema with scopes/requirements and computed stepper review step. |
| `selected_options` | Selected stable option keys. |
| `values` | Persisted answers grouped by step key. |
| `requires_authentication` | Current-step authentication flag. |
| `authentication_required` | Remaining form-level authentication requirement. |
| `completed_at` | Submission timestamp; omitted before submission. |
| `resume_token` | Guest token; returned only on guest creation. |

List responses use `data` plus pagination `meta` (`current_page`, `last_page`, `per_page`, `total`).
The current list endpoint returns drafts only (10 per page by default); use an authorized
application query with `Form::query()->submitted()` to build a submitted-form listing.
Null top-level fields are omitted. Submitted forms remain stored, and ordinary update/submit
operations reject forms that are no longer drafts. No automatic draft expiry or deletion runs.

## Using the package on a website

Install into your real Laravel application; Testbench/Workbench is not needed at runtime.
Register a builder and render its returned schema with your own Blade/Livewire or JavaScript page.
The package does not provide a web page or automatically add a navigation item.

For session-authenticated browser requests, configure the package endpoints to use the application's
web middleware instead of the default API-only stack:

```php
'routes' => [
    'enabled' => true,
    'prefix' => 'forms-api',
    'middleware' => ['web'],
    'name' => 'form-stepper.forms.',
],
```

Use the session's authenticated user; include a CSRF token for modifying requests. If you add
`auth` middleware, guests cannot use these endpoints. For API clients, configure Sanctum or your
chosen guard/middleware explicitly; do not assume the default `api` middleware authenticates users.

Include `<meta name="csrf-token" content="{{ csrf_token() }}">` in your Blade layout:

```js
const response = await fetch('/forms-api', {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
    },
    body: JSON.stringify({ type: 'contact', options: [] }),
});
const result = await response.json();
if (!response.ok) {
    throw new Error(result.message ?? `Form request failed (${response.status})`);
}
// Render result.definition, retain its id, and securely keep any guest resume_token.
```

For subsequent guest requests include the resume-token header as well. Render controls from
the schema, show field validation errors, save the current step before advancing, and build the
review screen from `values`. The engine does not expose a dedicated previous-step editing endpoint;
the step-saving endpoint only accepts the current step.

## Direct service usage

Use services when integrating through your own controllers or Livewire actions:

```php
use App\Forms\ContactFormBuilder;
use FormStepper\FormStepper\Services\FormService;

$builder = app(ContactFormBuilder::class);
$service = app(FormService::class);
$request = request();
$builder->authorize('create', $request);

$created = $service->create(
    $builder,
    [],
    $builder->requester($request),
    $builder->tenant($request),
);

$form = $created['form'];
$payload = $created['result']->toArray();
$saved = $service->saveStep($form, 'contact-details', [
    'name' => 'Ada',
    'email' => 'ada@example.test',
])->toArray();
```

Service methods also include `updateOptions($form, $builder, $keys)`,
`submitSingle($form, $valuesByStep)`, `complete($form)`, and
`claimGuest($form, $requester, $builder, $tenant)`.
Direct calls do not run controller authorization or verify a guest token for you. Perform those
checks before invoking the service; protect every read/write with application policies or builder
authorization. `create()` returns `form`, `result`, and `resume_token`; save/update/submit methods
return an arrayable `FormResult`. `claimGuest()` returns void.

## Upgrading existing installations

**Back up the database first. Do not run `migrate:fresh` against application data.**

The new migration is `database/migrations/2026_10_06_000000_update_form_ownership.php`.
Copy only this upgrade migration into your application's migration directory, using a timestamp
after its existing form migrations, then run `php artisan migrate`. Do not republish or rerun
the renamed initial migration against existing tables.

The upgrade targets the earlier dynamic-form engine schema. It:

1. Removes `creator_type`/`creator_id` and their indexes/foreign keys.
2. Renames `current_step` to `current_step_id`.
3. Converts `completed` statuses to `submitted`.
4. Adds tenant morph columns if enabled and absent.
5. Preserves requester data, saved values, and existing tenant identities.

It rejects conflicting step columns, partial tenant identities, and unknown statuses instead of
guessing. Review custom schemas separately. **The upgrade is irreversible because creator data is
deleted; `down()` throws rather than silently pretending to restore it.** Restore a backup for rollback.

If tenant support is enabled later after this migration already ran without it, add nullable string
`tenant_type` and `tenant_id` columns with a composite index in a new application migration first.
Changing the config does not rerun previously applied migrations.

Update callers/clients: remove the creator service argument, use `current_step_id`, and handle
`submitted` instead of `completed`. The `completed_at` timestamp name is unchanged.

## Development and troubleshooting

```bash
composer install
composer test
```

Individual checks: `composer analyse`, `composer lint:check`, `composer test:types`, and
`composer test:unit`. Package tests force isolated SQLite in memory, not your application database.
Tests include schema/option compatibility, persistence, guest ownership, tenant context isolation,
submission, and migration upgrades.

`composer serve` runs the optional Workbench development app, **not** your installed application.
Its build includes `db-wipe`/`migrate-fresh`: use a disposable database only. No demo builder or full
renderer is registered by default.

| Problem | Check |
|---|---|
| Composer cannot find the package | Add the GitHub VCS repository and use the exact new name plus `dev-main`. |
| Composer rejects Laravel/PHP versions | Use Laravel 12/13 and PHP 8.3+; do not bypass platform/security checks. |
| Builder not registered | Register its class under `builders` and match its `formType()` key. |
| Guest request returns 403 | Keep the creation token and send it in `X-Form-Resume-Token`. |
| Logged-in user appears as guest | Configure session/API guard middleware for the package routes. |
| Tenant resolution error | Check the configured relationship, persisted tenant, `TenantForm`, and membership. |
| Tenant columns missing | Enable tenants before fresh migration or add columns through an upgrade migration. |
| Schema unexpectedly empty | Check active type, available-type interfaces, selected options, and scope dimensions. |
| Step cannot be saved | Only `current_step_id` may be saved; review is submitted through `/complete`. |

## Contributing, security, and license

- [Contribution guide](.github/CONTRIBUTING.md)
- [Security policy](.github/SECURITY.md): report vulnerabilities privately, not in public issues.
- [Changelog](CHANGELOG.md)
- [MIT license](LICENSE.md)

Maintained by [Haitham Maznai](https://github.com/HaithamMaznai7).
Originally bootstrapped from [Laravel Package Skeleton](https://github.com/laravel/package-skeleton).
