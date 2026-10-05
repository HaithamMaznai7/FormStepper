---
name: skeleton-development
description: >
  Configure and apply the :package_name package in Laravel applications.
license: MIT
metadata:
  author: :author_name
---

# :package_name

Use this skill when a Laravel application needs to integrate the :package_name package.

## Primary Goal

- Configure application-owned form builders and option models, then use the package API to create,
  resume, and complete durable forms.

## Workflow

### 1. Install and configure

- Install `:vendor_slug/:package_slug` with Composer.
- Publish the configuration and migrations with `php artisan vendor:publish --tag=":package_slug-config"` and
  `php artisan vendor:publish --tag=":package_slug-migrations"`.
- Set table names and route settings before running `php artisan migrate`.

### 2. Register each workflow builder

- Extend `VendorName\Skeleton\Forms\FormBuilder` for each form type.
- Implement `formType()` and optionally override `mode()`, `steps()`, `resolveOptions()`, and the
  requester, creator, tenant, authorization, and listing-scope methods.
- Register each builder class under the `builders` config key.

### 3. Provide option schemas and scope context

- Make application option models implement `ProvidesFormRequirements`. Return stable option keys,
  requirement steps, required option keys, compatible option keys, and excluded option keys.
- Make requester and tenant models implement `ProvidesAvailableTypes` when their available form
  types should restrict the schema.
- Use stable step and input keys. Configure scope lists with form-type keys; leave a scope null or
  omit it to allow every type.
- Correct existing `tenant-types` model class names and duplicate JSON keys before using the
  compatibility adapter.

### 4. Connect the UI to the JSON API

- Create a draft with `POST /api/forms`, read or resume it with `GET /api/forms/{uuid}`, save
  stepper answers with `PUT /api/forms/{uuid}/steps/{step}`, and finish using the review endpoint
  `POST /api/forms/{uuid}/complete`.
- Use `POST /api/forms/{uuid}/submit` for single-mode forms. Use
  `PATCH /api/forms/{uuid}/options` when selected options change.
- Store the guest `resume_token` securely and return it in `X-Form-Resume-Token` when resuming a
  guest draft. A logged-in user can claim that draft through the same token.
- When claiming a draft, the builder's `prefillValues()` hook fills missing requester fields while
  preserving values the guest already entered.
- Mark authentication-gated steps with `requires-authentication: true`; guest drafts remain saved
  but cannot be submitted until claimed by an authenticated requester.
- Render the returned arrayable schema with the host app's chosen UI; option catalogs and any
  dynamic choice endpoints remain application-owned.

## References

- `src/Forms/FormBuilder.php`
- `src/Contracts/ProvidesFormRequirements.php`
- `src/Contracts/ProvidesAvailableTypes.php`
- `config/skeleton.php`
- `routes/skeleton.php`
- `README_PACKAGE.md`

## Examples

```php
final class OrderFormBuilder extends \VendorName\Skeleton\Forms\FormBuilder
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
