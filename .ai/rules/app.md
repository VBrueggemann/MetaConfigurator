---
paths:
  - 'app/**'
---

# App

## Queries hold non-state-changing domain logic, return a builder
Split domain logic by whether it changes state:
- State-changing logic goes in an invokable Action under `app/Actions/` (see the Actions rule above).
- Read-only/querying domain logic goes in an invokable Query class under `app/Queries/`, same shape as an Action (one public `__invoke()`, verb/noun-first descriptive name), but it must return an Eloquent `Builder` (or query builder) rather than executing the query or hydrating results itself — let the caller decide whether to `->get()`, paginate, further constrain, etc. This is absolute, with no exception for convenience: if a read needs post-hydration shaping (e.g. "most recent wins" dedup, computing a derived selection), that shaping logic gets its own separate, independently-testable class rather than being folded into the Query.
- A Query's `__invoke()` takes explicit domain arguments only (models, ids, value objects) — never a caller-specific object like a Livewire component (`$this`). This is what keeps it callable from a future non-Livewire consumer (e.g. an API), not just a relocation that still assumes one call site's request lifecycle.
- When several call sites need the same shape of query (e.g. `whereIn('id', ...)` with only the trailing filter differing), consolidate into one parameterized Query class rather than one per call site — that's exactly the case the "return `Builder`, caller constrains further" contract exists for.
- Resolve Query classes the same way as Actions: via the container, not `new`'d directly outside tests.
- Don't reach for a Query class (or any query) at all if the data is already sitting in already-loaded state — e.g. a value the pipeline already computed and handed back, or something cached on the caller from an unavoidable earlier query in the same request. Extracting a query into a well-named class is only the second-best option to not needing one.

## Eloquent Collection's only() silently discards custom keys — use whereIn() to filter a keyed collection
`Illuminate\Database\Eloquent\Collection::only($keys)` is not the same method as base `Illuminate\Support\Collection::only()`: the Eloquent version matches against the model's own primary key via its internal dictionary and returns `array_values(...)` of the result, which **reindexes to 0, 1, 2, ...** regardless of any custom `keyBy()` applied beforehand. If code depends on a collection staying keyed by something after filtering (e.g. `$views->keyBy('id')->only($someIds)->keys()` expecting UUIDs back), the keys silently become integers instead — this caused a real bug (UUID-typed `whereIn()` binding failing with "invalid input syntax for type uuid" on the reindexed values). Use `->whereIn('id', $ids)` instead of `->only($ids)` to filter an Eloquent collection while preserving its existing keys — `whereIn()` isn't overridden and behaves like the base Collection's key-preserving filter.

## No prose comments explaining what code does
Don't add inline comments narrating what a line/block does when the code is already clear from naming. Only comment a genuinely non-obvious WHY (a hidden constraint, a subtle invariant, a workaround) — never a WHAT.

## No descriptions in PHPDoc annotations
PHPDoc tags (`@param`, `@return`, `@var`, `@throws`, etc.) must not carry a trailing description — type/name only, e.g. `@param string $name` not `@param string $name The user's name`. Don't replace it with an inline comment either — if the name and type aren't enough, that's fine; no comment at all.

## Prefer global helpers over facades or direct class instantiation
Use Laravel's global helper functions instead of the equivalent facade or `new`'d class where one exists, e.g. `collect()` not `new Collection` / `Collection::make()`, `now()` not `Carbon::now()`, `app()` not `App::make()`, `response()` not `Response::make()`. Keeps call sites terse and consistent with the rest of the codebase. Only reach for the facade/class directly when no helper covers the needed API (e.g. a specific Collection method not exposed as a helper, or when you need the class name for a type hint).

## No comments in code, not even non-obvious WHY ones
This file's general "only WHY comments" guidance is superseded for this project: the user has explicitly asked for zero comments in code, including a justified non-obvious-WHY explanation for a real bug workaround (a Livewire <select multiple> quirk). Default to no comments at all — put explanations in the PR/commit description or chat response instead.
