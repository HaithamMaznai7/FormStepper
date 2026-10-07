---
name: package-compatibility
description: "Use this skill when reviewing Laravel package compatibility across composer constraints, PHP versions, Laravel versions, Testbench versions, dependency stability lanes, Windows CI, or matrix-sensitive code and workflow changes."
license: MIT
metadata:
  author: laravel
---

# Package Compatibility

## Primary Goal

Keep package code, dependencies, and workflows compatible with the supported Laravel 10.48.29+/11/12/13 and PHP 8.3+ matrix.

## Workflow

1. Read `composer.json` first to determine PHP, Laravel, and Testbench constraints.
2. Check changed code against Laravel 10/11/12/13 APIs and PHP 8.3+ syntax before adopting newer framework or language features.
3. Review `.github/workflows/tests.yml` for dependency stability lanes, prefer-lowest coverage, prefer-stable coverage, and Windows concerns.
4. When changing dependencies, confirm constraints still allow the intended Laravel and Testbench versions.
5. Validate with the smallest local command available, then rely on CI for full OS and dependency matrix coverage.

## References

- `composer.json`
- `.github/workflows/tests.yml`
- `phpstan.neon.dist`
- `tests/`
- `workbench/`

## Examples

- Review a new Laravel API call by checking whether it exists in Laravel 10, 11, 12, and 13 before merging it into shared package code.
- Use `$casts` properties for Laravel 10 models; `casts()` is only supported on Laravel 11+.
- Laravel 10 uses Testbench 8, Pest 2, and Larastan 2. Exclude PAO in legacy test lanes.
- SQLite ownership upgrades on Laravel 10 need Doctrine DBAL 3. Keep advisory bypasses limited to legacy testing.
- Run functional and type-coverage tests on Laravel 10; run static analysis on modern lanes because relation generic annotations differ between Larastan 2 and 3.
- Review a dependency bump by checking Composer constraints, Testbench constraints, prefer-lowest behavior, and Windows path assumptions.

## Anti-Patterns

- Assuming the latest local dependency version represents the whole support matrix.
- Adding PHP syntax or Laravel APIs that exceed `composer.json` constraints.
- Ignoring Windows path separators, executable assumptions, or shell-only syntax in tests and workflows.
- Removing dependency stability lanes because they are slower than a single happy path.
