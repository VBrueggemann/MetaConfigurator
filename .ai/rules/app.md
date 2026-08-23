---
paths:
  - 'app/**'
---

# App

## Queries hold non-state-changing domain logic, return a builder
Split domain logic by whether it changes state:
- State-changing logic goes in an invokable Action under `app/Actions/` (see the Actions rule above).
- Read-only/querying domain logic goes in an invokable Query class under `app/Queries/`, same shape as an Action (one public `__invoke()`, verb/noun-first descriptive name), but it must return an Eloquent `Builder` (or query builder) rather than executing the query or hydrating results itself — let the caller decide whether to `->get()`, paginate, further constrain, etc.
- Resolve Query classes the same way as Actions: via the container, not `new`'d directly outside tests.

## No prose comments explaining what code does
Don't add inline comments narrating what a line/block does when the code is already clear from naming. Only comment a genuinely non-obvious WHY (a hidden constraint, a subtle invariant, a workaround) — never a WHAT.

## No descriptions in PHPDoc annotations
PHPDoc tags (`@param`, `@return`, `@var`, `@throws`, etc.) must not carry a trailing description — type/name only, e.g. `@param string $name` not `@param string $name The user's name`. Don't replace it with an inline comment either — if the name and type aren't enough, that's fine; no comment at all.
