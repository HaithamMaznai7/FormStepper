# Form Stepper

This repository is a Laravel package. Keep the package focused, idiomatic, and easy for Laravel developers to install, test, and maintain.

## Package Conventions

- Use Laravel-native package APIs and the existing service provider shape before adding abstractions.
- Keep package names, namespaces, Composer metadata, publish tags, documentation, and examples aligned with `haitham-maznai/form-stepper`.
- Add only the files and dependencies needed for the package behavior being implemented.
- Prefer explicit Laravel package code over helper abstractions unless the extension point is real.
- Keep tests focused on observable package behavior through public APIs, service provider wiring, commands, routes, published resources, and documentation promises.
- Form definitions are application-owned: register a `Forms\FormBuilder` per form type, and have selected option models implement `Contracts\ProvidesFormRequirements`.
- Requester and tenant models should implement `Contracts\ProvidesAvailableTypes` when their available form types constrain schema scopes.
- Keep scope checks server-side. `types-scope`, `tenant-types`, and `requester-scope` contain form-type keys; `guest-scope` contains `guest` or `authenticated`. Different scope dimensions are combined, and `null` means unrestricted.
- Forms are durable drafts until explicitly completed. Preserve step values when selections change if the corresponding step and input keys still exist.
- The JSON routes are configurable; host applications own authorization, tenant context resolution, and presentation.

## Quick Commands

- Full validation: `composer test`
- Formatting check: `composer lint:check`
- Static analysis: `composer analyse`
- Pest tests: `composer test:unit`
- Workbench build: `composer build`
- Workbench server: `composer serve`

## Local Skills

- `package-scaffold`: use when adding package capabilities or wiring them through the service provider, including commands, migrations, routes, config, views, translations, assets, middleware, publish tags, workbench files, and console-only behavior.
- `package-testing`: use when adding or changing package tests with Pest 4/5 and Orchestra Testbench.
- `package-release`: use when preparing changelog, release notes, tags, or GitHub release workflow changes.
- `package-compatibility`: use when reviewing code, dependencies, or CI against the PHP and Laravel support matrix.
- `package-generate-skill`: use when updating the bundled Boost skill from the package implementation, README, and examples.
