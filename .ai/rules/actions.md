# Actions, not service classes

Applies to: `app/**`

- Single-purpose business logic goes in an invokable Action class under `app/Actions/`, with one public `__invoke()` method and a descriptive verb-first name (e.g. `EvaluateRule`, not `RuleService`/`RuleEvaluator`).
- Resolve Actions via the container — constructor-inject them where the caller is itself container-resolved, or call `app(SomeAction::class)(...)` otherwise. Don't `new` them directly outside of tests.
- This is a deliberate project-wide convention (the user's explicit preference), not specific to the rules engine — apply it to new business logic generally, not just `app/Actions/Pipeline/`.
