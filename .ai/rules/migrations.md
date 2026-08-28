---
paths:
  - 'database/migrations/**'
---

# Migrations

## Domain tables use UUID primary keys
Domain tables define `$table->uuid('id')->primary()`, not auto-incrementing ids, and reference other domain tables via `foreignUuid(...)->constrained()`. Framework tables (e.g. `users`) keep the default auto-increment id.

## Don't write down() methods in migrations
Migrations in this project only implement `up()`. Omit the `down()` method entirely (don't stub it with `Schema::dropIfExists(...)`) — migrations here are not meant to be rolled back.

Enforced by `tests/Arch/MigrationsTest.php` — a plain (non-`arch()`) test, since each migration file returns an anonymous class rather than living in a namespace `arch()` can target; it `require`s every file under `database/migrations/` and asserts the returned instance has no `down` method.

## answers.value is jsonb, nullable
The answers.value column is jsonb (not json) and nullable. A PHP null PendingAnswer value must persist as real SQL NULL, not a JSON 'null' scalar — Eloquent's 'array' cast on Answer already does this correctly (setAttribute skips json_encode when the value is null), so don't add custom null-handling for it.
