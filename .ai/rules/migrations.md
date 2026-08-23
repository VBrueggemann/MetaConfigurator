---
paths:
  - 'database/migrations/**'
---

# Migrations

## Domain tables use UUID primary keys
Domain tables define `$table->uuid('id')->primary()`, not auto-incrementing ids, and reference other domain tables via `foreignUuid(...)->constrained()`. Framework tables (e.g. `users`) keep the default auto-increment id.
