---
paths:
  - 'tests/**'
---

# Tests

## Shared test helpers as global functions in Pest.php
Define reusable test-only helper functions (e.g. `answer()`) as global functions in `tests/Pest.php`, not as traits, base-class methods, or repeated inline setup.

## Integration-style tests, no mocking
Tests exercise real Action classes and a real database (RefreshDatabase + factories) rather than mocking collaborators with Mockery, even though mockery/mockery is installed.
