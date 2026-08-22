# Rules engine

Applies to: `app/**` (rules/pipeline/expression evaluation code)

- The rules engine is an `Illuminate\Pipeline\Pipeline`, run in the fixed order **visibility → validation → next-group**, threading one shared evaluation-result object each stage reads and adds to. Do not reintroduce an independent-events design for this — it was tried and reversed because the stages genuinely depend on each other's output (see `docs/adr/0003-rules-engine-as-dependent-pipeline.md`).
- `Rule.expression` strings are evaluated with `symfony/expression-language`, and may contain `&&`/`||` themselves for compound logic. There is no separate Condition model or conjunction column — don't reintroduce one (see `docs/adr/0006-view-unification-and-rule-condition-merge.md`).
- A Rule's `component` field says which pipeline stage owns it (visibility/validation/next-group). `meta` is a JSON payload whose shape the owning component defines — e.g. a next-group Rule's `meta` names the View to jump to.
- Auto-select is a recognized Rule component in the domain (see `CONTEXT.md`) but is intentionally unimplemented until a Configurator needs it. If you add it, it slots into the pipeline before validation.
