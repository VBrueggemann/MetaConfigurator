---
paths:
  - 'app/Models/**'
---

# Models

## Domain models use UUID primary keys
Domain models use `HasUuids` for UUID primary keys, not auto-incrementing integers. Framework tables (e.g. `users`) keep the default auto-increment id — this applies to app-owned domain models.

## Mass assignment via $fillable property
Declare mass-assignable fields with `protected $fillable = [...]` as a class property, not the `#[Fillable]`/`#[Hidden]` attribute style.

## Chaining orderBy on top of a relation that already has one silently no-ops
`Configuration::relevantAnswerChunks()` bakes in `->orderBy('sequence')` (ascending) so consumers get chronological order by default. Appending `->orderByDesc('sequence')` (or any second `orderBy` on the same column) at the call site does **not** flip it — Eloquent just adds a second ORDER BY clause (`sequence asc, sequence desc`), and since `sequence` is already unique per configuration, the first clause alone fully determines row order, making the second a no-op. This caused a real bug: `PlayConfigurator::back()` returned the *earliest* chunk instead of the most recent one before the current position. To reverse a relation's built-in order, use `->reorder('sequence', 'desc')` (clears prior orders first), not a second `orderBy`/`orderByDesc` call.
