---
paths:
  - 'app/Models/**'
---

# Models

## Domain models use UUID primary keys
Domain models use `HasUuids` for UUID primary keys, not auto-incrementing integers. Framework tables (e.g. `users`) keep the default auto-increment id — this applies to app-owned domain models.

Enforced by `tests/Arch/ModelsTest.php` via `->toUseTrait(...)`, not `->toUse(...)`: `expect('App\Models')->toUse(HasUuids::class)` looks like the obvious expectation but is a namespace-aggregate check — it passes as soon as *any* model in the namespace uses the trait, so it silently stops enforcing anything the moment one real model has it (verified empirically: stripping `HasUuids` from one model while others kept it still passed). `toUseTrait()` is the actual per-class (for-all) check for trait usage; `->ignoring('App\Models\User')` carves out the one framework model.

## Mass assignment via $fillable property
Declare mass-assignable fields with `protected $fillable = [...]` as a class property, not the `#[Fillable]`/`#[Hidden]` attribute style.

## Chaining orderBy on top of a relation that already has one silently no-ops
`Configuration::relevantAnswerChunks()` bakes in `->orderBy('sequence')` (ascending) so consumers get chronological order by default. Appending `->orderByDesc('sequence')` (or any second `orderBy` on the same column) at the call site does **not** flip it — Eloquent just adds a second ORDER BY clause (`sequence asc, sequence desc`), and since `sequence` is already unique per configuration, the first clause alone fully determines row order, making the second a no-op. This caused a real bug: `PlayConfigurator::back()` returned the *earliest* chunk instead of the most recent one before the current position. To reverse a relation's built-in order, use `->reorder('sequence', 'desc')` (clears prior orders first), not a second `orderBy`/`orderByDesc` call.

## Attribute-accessor `get` closures receive the value keyed by the literal access string, not the underlying column
A `protected function fooBar(): Attribute` accessor is reachable via both `$model->foo_bar` and `$model->fooBar` (Eloquent resolves the method via `Str::camel($key)` for either spelling), but the raw `$value` handed to the `get`/`set` closure is `$this->attributes[$key] ?? null` using the *exact literal key that was accessed* — it is never normalized to the real snake_case column first. So if the closure just does `get: fn ($value) => ...`, accessing via the "wrong" spelling (whichever one isn't the real DB column, e.g. `isBaked` when the column is `is_baked`) silently receives `null` every time, regardless of the real stored value — a real bug hit while trying a camelCase `Configurator::isBaked()` wrapper around the `is_baked` column (since removed in favor of just reading `is_baked` directly).

Fix: when an accessor's whole purpose is to expose an existing snake_case column under a friendlier name, ignore the closure's `$value` parameter and read the real column explicitly: `get: fn (): bool => (bool) $this->attributes['is_baked']`. Don't define a `set` closure unless you also hardcode the real key inside it for the same reason — omitting `set` entirely makes plain `$model->update(['is_baked' => ...])` fall through to the default (correct) raw-assignment path.

## Templated string fields live in a separate `Template` model, not inline JSON
Four fields — `View.config['label']`, `['content']`, `['options'][].label`, and `Rule.meta['message']` — are `{{ }}`-templatable text extracted into their own polymorphic `templates` table (`morphMany` on `View` and `Rule` only — not `Configurator`, which has no templatable field). `key` discriminates which field a row holds (`label`/`content`/`option_label`/`message`); `order` is null for the first three (one row per View/Rule) and holds the option's array index for `option_label` (a View can have several, one per `config['options']` entry). `config['options']` itself stores everything except `label` (`value`/`price`/`icon`/etc.) — the label is reconstructed onto `config['options'][$index]['label']` only at resolve time (see `pipeline.md`). Create these via `ViewFactory`'s `label`/`content`/`options` params or `RuleFactory::message()` — both insert the `Template` row via `afterCreating()` — rather than writing into `config`/`meta` directly.
