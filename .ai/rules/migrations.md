---
paths:
  - 'database/migrations/**'
---

# Migrations

## Domain tables use UUID primary keys
Domain tables define `$table->uuid('id')->primary()`, not auto-incrementing ids, and reference other domain tables via `foreignUuid(...)->constrained()`. Framework tables (e.g. `users`) keep the default auto-increment id.

## Don't write down() methods in migrations
Migrations in this project only implement `up()`. Omit the `down()` method entirely (don't stub it with `Schema::dropIfExists(...)`) — migrations here are not meant to be rolled back.
