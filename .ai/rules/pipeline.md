---
paths:
  - 'app/Actions/Pipeline/**'
---

# Pipeline

## `ConfigurationEvaluation` holds almost nothing beyond the scope tree

`componentContexts['scope']` (the anchor's `bloodline->toTree()`, set by `ApplyFlow`) is the single source of truth for views, rules, and (after `ApplyVisibility`/`ApplyValidation` run) which of those are currently relevant. `componentContexts['visibility']`, `['validation']`, and `['pending_views']` do not exist — each was replaced by pruning the tree itself rather than a side-map:

- `ApplyVisibility` computes its visibility map over `componentContexts['scope']` itself (see below), then walks that same tree and cascading-removes any node the map marks invisible, mutating `children` in place. A view that isn't in the array is, by definition, not visible — there is nothing left to check.
- `ApplyValidation` prunes *passing* `component === Validation` rules off each scope node's `rules` collection during its own recursive walk (Visibility/Flow rules on the same collection are untouched). Whatever Validation rules remain on a node *are* its current errors — read `$rule->meta['message']` directly, there is no separate error map. A view that was never "pending" (see below) has all its Validation rules removed regardless of whether they'd fail, since nothing should be reported for it.
- "Pending" (was this view just submitted, directly or via a descendant) is computed once per `ApplyValidation` run from `$evaluation->pendingAnswers`' `path` strings — matching the same `bloodline` `path` column `ApplyFlow` used to compute the now-removed `pending_views` context.

`componentContexts['breadcrumbs']` stays a fully separate concern — it's built from `AnswerChunk` history (`ApplyBreadcrumbs`), not derived from the tree at all. `context['calculation']` (named formula results) also stays separate — formulas aren't view-keyed.

`Collection::toTree()`/`toList()` (vendor macro + app-registered macro on `Staudenmeir\LaravelAdjacencyList\Eloquent\Collection`, shared across the whole Collection hierarchy since `Illuminate\Support\Collection` is the only class in that chain that actually declares the `Macroable` trait) are how anything needs to flatten the tree — never hand-roll a recursive `children` walk.

## Cross-scope references: `answer(id)`/`label(id)`/`validationError(id)`, not array access

A view/rule can reference another view **anywhere in the configurator**, not just within the current scope bloodline — a Rule expression, a `{{ }}` template, or a `Calculation` formula. These are ExpressionLanguage functions, generically registered by `ExpressionLanguageServiceProvider` from the `config('templating.functions')` registry (keyed by model class, currently only `View::class`) — never hand-added one-off in the provider.

Adding a new such function (e.g. a hypothetical `placeholder(id)`/`leafCount(id)`) is a single edit to that registry: `['with' => [...extra eager loads...], 'resolve' => fn (View $view, array $context) => ...]`. Nothing else needs touching — `CollectAndBatchFetchExternalReferences` derives both which function names to scan for and which relations to eager-load from this same registry.

- `CollectAndBatchFetchExternalReferences` runs right after `ApplyFlow` (once the scope tree exists) and before anything evaluates an expression. It statically regex-scans every in-scope Rule expression/`meta.message`, View `config.label/content/options[].label`, and the configurator's calculation formulas for these function calls (same technique `ApplyCalculation::topologicalOrder()` already used for `calculation.<name>` refs) — this is a scan of literal string arguments, not real evaluation, so a dynamically-computed id (e.g. `answer(otherAnswer())`) won't be discovered.
- Referenced ids already in scope need no query. Referenced ids outside scope get one batch `View::whereIn('id', ...)->with($unionOfEveryRegisteredWith)->get()`. Both sets land in `$evaluation->context['modelsById'][View::class]`, keyed by id — this is what every registered function's `resolve` closure reads its model from (via `ResolveModelFunction`, which also owns the circular-reference guard, generically, for any function).
- The same stage also fetches the `Calculation` component's formulas (once) and stashes them in `$evaluation->context['calculationFormulas']` so `ApplyCalculation` doesn't re-query the same row.

`answer(id)` is mode-aware, not two functions: it reads `$context['__persistedOnly']` to decide whether to preview `$context['pendingAnswers']` over `$context['relevantAnswers']`/`relevantAnswerChunks`. Everything except `ApplyFlow`'s own flow-rule evaluation wants pending-aware (preview unsaved input); `ApplyFlow` builds its own local context with `__persistedOnly => true` so a Flow rule never jumps based on an answer that hasn't been committed yet — same guarantee the old `context['persistedAnswers']` vs `context['answers']` split gave, just carried as a context ingredient instead of two differently-populated array keys. `answer(id)` on a Group (or any view with children) aggregates its own answer *and* every descendant's, joined with `, ` — this is why its registry entry declares `'with' => ['descendants']`. If nothing is found anywhere in that subtree, it falls back to `true` when the id anchors a relevant `AnswerChunk` (a Group never gets a physical `Answer` row — see the rules-engine rule).

Any unresolved reference (unknown id, not-yet-answered, or a circular reference) falls back to printing the raw `{{ ... }}` text — never throws past `ResolveTemplate`'s own catch. `content` escapes only the substituted *value* (`escapeValues: true`); `label`/`options[].label`/a rule's `message` must NOT be pre-escaped, since Blade's own `{{ }}` already escapes the whole resolved string at render time.

`ResolveTemplateStrings` itself has zero model-specific logic — `config('templating.resolvable')` is a flat list of `{context, iterate, resolve, keys}` entries, each naming a `componentContexts` key to walk (currently always `scope`, since rule messages live nested under `View->rules` in the same tree). Add a new self-resolving key under an existing entry's `keys`, or a whole new list entry for a different field, never inline in `ResolveTemplateStrings` or in whatever consumes the resolved value.

`ResolveTemplateStrings` mutates `componentContexts['scope']` Views (and their loaded `rules`) in memory, never persists, and runs in *both* pipelines, before `ApplyValidation` — a rule's `meta.message` is already template-resolved by the time `ApplyValidation` might keep that rule around as a current error.

## ApplyVisibility computes from the scope tree, not a separate full-tree query
`ApplyVisibility` no longer runs its own `rootView->descendantsAndSelf()` query. It flattens the already-loaded `componentContexts['scope']` (the anchor's bloodline, set by `ApplyFlow`) via `Collection::toList()` and computes visibility over that. The anchor view (`$evaluation->anchorViewId`) is exempt from its own Visibility rules (`$ownRuleVisible` is forced true for it) so Flow's chosen "current step" is never pruned out from under it — ancestors/descendants still cascade normally via `$parentVisible`.

This relies on `pendingAnswers` viewIds always being drawn from the current anchor's bloodline (true today: `PlayConfigurator` only ever renders/binds fields within `componentContexts['scope']`). If a future caller ever introduces a pendingAnswer for a view outside the anchor's bloodline, it will now be silently dropped by the `$visibility[$answer->viewId] ?? false` filter instead of possibly being kept — revisit this if that assumption ever changes.
