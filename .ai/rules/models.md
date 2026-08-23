---
paths:
  - 'app/Models/**'
---

# Models

## Domain models use UUID primary keys
Domain models use `HasUuids` for UUID primary keys, not auto-incrementing integers. Framework tables (e.g. `users`) keep the default auto-increment id — this applies to app-owned domain models.

## Mass assignment via $fillable property
Declare mass-assignable fields with `protected $fillable = [...]` as a class property, not the `#[Fillable]`/`#[Hidden]` attribute style.
