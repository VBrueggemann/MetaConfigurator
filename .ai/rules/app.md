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

## Prefer global helpers over facades or direct class instantiation
Use Laravel's global helper functions instead of the equivalent facade or `new`'d class where one exists, e.g. `collect()` not `new Collection` / `Collection::make()`, `now()` not `Carbon::now()`, `app()` not `App::make()`, `response()` not `Response::make()`. Keeps call sites terse and consistent with the rest of the codebase. Only reach for the facade/class directly when no helper covers the needed API (e.g. a specific Collection method not exposed as a helper, or when you need the class name for a type hint).

## No comments in code, not even non-obvious WHY ones
This file's general "only WHY comments" guidance is superseded for this project: the user has explicitly asked for zero comments in code, including a justified non-obvious-WHY explanation for a real bug workaround (a Livewire <select multiple> quirk). Default to no comments at all — put explanations in the PR/commit description or chat response instead.
