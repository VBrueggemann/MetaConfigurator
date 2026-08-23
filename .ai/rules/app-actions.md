---
paths:
  - 'app/Actions/**'
---

# App Actions

## Create related models through their Eloquent relation, not the model directly
When an Action creates a model that belongs to an already-known parent, create it via the parent's relation method (e.g. `$configurator->configurations()->create([...])`) instead of `Configuration::create(['configurator_id' => $configurator->id])`. Keeps the foreign key wiring on the relation, not repeated inline at each call site.

## Look up a model through its parent relation, not by bare id
When resolving a model by an id that came from an untrusted source (session, request input) and you already have its parent in hand, look it up via the parent's relation (e.g. `$configurator->configurations()->find($id)`) instead of `Model::query()->where('id', $id)->first()`. This scopes the lookup to the parent for free, so a stale or mismatched id (e.g. belonging to a different parent) naturally resolves to null instead of returning someone else's record.
