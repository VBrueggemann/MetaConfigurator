---
paths:
  - 'app/**'
---

# App

## Queries hold non-state-changing domain logic, return a builder
Split domain logic by whether it changes state:
- State-changing logic goes in an invokable Action under `app/Actions/` (see the Actions rule above).
- Read-only/querying domain logic goes in an invokable Query class under `app/Queries/`, same shape as an Action (one public `__invoke()`, verb/noun-first descriptive name), but it must return an Eloquent `Builder` (or query builder) rather than executing the query or hydrating results itself — let the caller decide whether to `->get()`, paginate, further constrain, etc.
- Resolve Query classes the same way as Actions: via the container, not `new`'d directly outside tests.
