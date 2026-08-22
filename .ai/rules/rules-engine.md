# Rules engine

Applies to: `app/**` (rules/pipeline/condition evaluation code)

- The rules engine is an `Illuminate\Pipeline\Pipeline`, run in the fixed order **Visibility → Validation → NextGroup**, threading one shared evaluation-result object each stage reads and adds to. Do not reintroduce an independent-events design for this — it was tried and reversed because the stages genuinely depend on each other's output (see `docs/adr/0003-rules-engine-as-dependent-pipeline.md`).
- `Condition.rule` expressions are evaluated with `symfony/expression-language`. Don't introduce a second condition grammar or a structured/JSON-logic representation alongside it (see `docs/adr/0002-symfony-expression-language-for-conditions.md`).
- `AutoSelectRule` is a recognized Rule kind in the domain (see `CONTEXT.md`) but is intentionally unimplemented until a Configurator needs it. If you add it, it slots into the pipeline before Validation.
