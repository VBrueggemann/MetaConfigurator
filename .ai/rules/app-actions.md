---
paths:
  - 'app/Actions/**'
---

# App Actions

## Create related models through their Eloquent relation, not the model directly
When an Action creates a model that belongs to an already-known parent, create it via the parent's relation method (e.g. `$configurator->configurations()->create([...])`) instead of `Configuration::create(['configurator_id' => $configurator->id])`. Keeps the foreign key wiring on the relation, not repeated inline at each call site.
