---
paths:
  - '.ai/rules/**'
---

# Rules

## Check whether a new/edited rule is expressible as a Pest arch test before writing it as prose
Before adding or editing a `.ai/rules/*.md` entry that constrains class shape/placement/naming (not business logic, config values, or Blade-only behavior), check whether `pestphp/pest-plugin-arch`'s built-in expectations can enforce it mechanically instead of relying on an agent re-reading prose each time. See `tests/Arch/*.php` for the established pattern and precedent (Livewire no-DB-queries, Actions/DTO/Queries `__invoke()` split, Models' `HasUuids`, migrations' no `down()`).

If expressible: add the arch test (or a plain Pest test if the target isn't a namespaced class, e.g. migrations), verify it actually fails on a real violation (not just passes vacuously) before trusting it, and shrink the rule's prose to point at it ("Enforced by `tests/Arch/...`") instead of restating the constraint.

If not expressible: leave it as prose and note briefly why, so the next pass doesn't re-litigate it.

Two traps hit while doing this, worth re-checking each time:
- `expect(namespace)->toUse(X)` is a namespace-*aggregate* check (passes if *any* class in the namespace uses X) — not a per-class check. Use `->toUseTrait(X)` for "every class in this namespace uses trait X"; `toHaveMethod`/`toExtend`/`toImplement`/etc. are genuine per-class ("targeted") checks.
- A new `tests/<Dir>/` must be added to `phpunit.xml`'s `<testsuites>` or it silently never runs under plain `sail artisan test` — confirm the full-suite test count actually increases, not just that the new file passes when targeted directly.

Some rules genuinely aren't expressible with the installed `pest-plugin-arch` version (e.g. "exactly one public method", verb-first naming, usage restricted to a specific call site distinguishing `->through()` arrays from constructor injection) — don't force a brittle approximation; check the installed version's actual capabilities (`composer show pestphp/pest-plugin-arch`) before concluding either way.
