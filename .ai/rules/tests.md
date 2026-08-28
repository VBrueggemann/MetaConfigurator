---
paths:
  - 'tests/**'
---

# Tests

## Shared test helpers as global functions in Pest.php
Define reusable test-only helper functions (e.g. `answer()`) as global functions in `tests/Pest.php`, not as traits, base-class methods, or repeated inline setup.

## Integration-style tests, no mocking
Tests exercise real Action classes and a real database (RefreshDatabase + factories) rather than mocking collaborators with Mockery, even though mockery/mockery is installed.

## A new top-level tests/ directory must be added to phpunit.xml's testsuites
phpunit.xml's `<testsuites>` list is an allowlist (currently Unit, Feature, Arch) — `vendor/bin/sail artisan test` with no path only runs directories named there. A new top-level directory under `tests/` (e.g. a future `tests/Browser`) silently never runs in the default suite until it's added as its own `<testsuite>` entry, even though `sail artisan test tests/NewDir` runs it fine when pointed at directly. Verified this the hard way: `tests/Arch/*.php` passed every time it was run directly, but was silently absent from the "168 passed" full-suite count until phpunit.xml gained an `Arch` testsuite entry (count only then jumped to 186). Always confirm a new test directory shows up in the plain `sail artisan test` total, not just when targeted directly.
