# Release Notes

## Unreleased

## v1.1.4 - 2026-10-07

- Remove the hardcoded Composer version so Packagist derives releases from Git tags.
- Document how to resolve ignored release tags without rewriting published tags.
- Release validation: static analysis, formatting, and 100% type coverage pass.
  The existing extra-values rejection test still conflicts with the configured
  `throw_on_extra_values = false` behavior; this metadata-only release does not change it.

## Previous development

- Persistent single-step and stepper workflows, dynamic option requirements, and JSON endpoints.
- Guest continuation and requester ownership with optional tenant context and `TenantForm`.
- Ownership upgrade migration, `current_step_id`, and `submitted` status.
- Composer package name `haitham-maznai/form-stepper` and standalone
  [GitHub repository](https://github.com/HaithamMaznai7/FormStepper).
- Installation, schema, website integration, API, and upgrade documentation.

Earlier tags contained inconsistent Composer version metadata and may be ignored by Packagist.
