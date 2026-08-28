# TODO

Backlog of known-but-not-yet-scheduled work. Each item names the trigger for picking it up.

## Pipeline performance (`app/Actions/Pipeline/**`)

Found via profiling on 2026-08-27. Context: `FetchConfigurationState`/`CommitConfigurationAnswers`
run the full stage pipeline on every navigation *and* on every `wire:model.live` field change
(default for most widgets unless a view's `config['updateOn']` is `blur`) — see
`app/Livewire/PlayConfigurator.php`'s `updatedValues()`.

- [x] **`ApplyFlow` loads the entire configurator tree on every request, not just the current scope.**
      Fixed 2026-08-27: `ApplyFlow` no longer eager-loads `$with`/`rules` onto the full tree. The full-tree
      fetch (still one recursive-CTE query — `path`/`depth` aren't materialized columns, so any depth-first
      position lookup needs it) now selects only what navigation needs. The backward flow-rule scan is
      chunked (`app/Actions/Pipeline/ApplyFlow.php` — `findFlowTarget`, `FLOW_RULE_CHUNK_SIZE`) and batch-fetches
      only `component = Flow` rules for the candidate slice, stopping at the first firing rule instead of
      loading rules for the whole tree. `$with` for a referenced view is now resolved lazily per matched rule
      (`loadReferencedRelations`), reusing the scan approach `CollectAndBatchFetchExternalReferences` already
      used. Worst case (nothing answered yet) still scans the remaining tree — that's inherent to "prefer the
      latest firing rule" semantics, not a query-shape problem.

- [x] **`answer()`'s `descendants` eager load is applied to every View, not just Groups.**
      Fixed 2026-08-27 alongside the item above. `config/templating.php`'s `answer` resolver now only reads
      `$view->descendants` when `$view->type === ViewType::Group`. `ApplyFlow` and
      `CollectAndBatchFetchExternalReferences` both split their eager-loaded relations into a common set and
      `GROUP_ONLY_RELATIONS = ['descendants']`, loading the latter only for referenced views that are actually
      Groups. See `tests/Feature/Pipeline/CollectAndBatchFetchExternalReferencesTest.php` ("does not load
      descendants for a non-group view referenced by answer()").

- [x] **`ExpressionLanguage` has no persistent parse cache.**
      Fixed 2026-08-27: `ExpressionLanguageServiceProvider` now constructs the singleton with a
      `Symfony\Component\Cache\Adapter\RedisAdapter` (namespace `expression_language`), wrapping the raw
      phpredis client from Laravel's `cache` Redis connection (`Redis::connection('cache')->client()`) —
      not `Cache::store('redis')`. Going through Laravel's `Cache` repository would run the `ParsedExpression`
      AST through `unserialize(..., ['allowed_classes' => $this->serializableClasses])`
      (`config/cache.php`'s `serializable_classes` is `false` by default), which strips every object in the
      tree down to `__PHP_Incomplete_Class` — allowlisting it correctly would mean enumerating every
      `Symfony\Component\ExpressionLanguage\Node\*` subclass and keeping that list in sync with the package.
      `RedisAdapter` does its own `serialize()`/`unserialize()` with no such allowlist, sidestepping the
      problem entirely; reusing Laravel's own Redis connection means no separate connection config. Added a
      `redis` service to `compose.yaml` (Sail didn't have one) — forwarded on host port 6380
      (`FORWARD_REDIS_PORT`), not the Sail default 6379, since another project's Redis container already
      holds that port on this machine. `CACHE_STORE` is now `redis` and `REDIS_HOST` is `redis` (was
      `127.0.0.1`, which never worked container-to-container). Verified the cache key lands in Redis db 1
      (`metaconfigurator-database-expression_language:...`) and the full pipeline test suite (153 tests)
      still passes.

      Investigated whether to also cache *calculation results* keyed by dependency, per request, refreshing
      when a dependency's answer changes — decided against for now. Formula dependencies (`answer(id)`,
      `label(id)`, `validationError(id)`, `calculation.<name>`) are staticly extractable with the same
      regex-scan technique `CollectAndBatchFetchExternalReferences` already uses, so a content-hash cache key
      would self-invalidate correctly (same accepted blind spot as elsewhere: a dynamically-computed id like
      `answer(otherAnswer())` isn't discoverable by a static scan). But with the parse cache in place,
      `evaluate()` against an already-parsed AST is a cheap in-process tree-walk — `answer()` itself is just
      an in-memory `Collection::whereIn()` over already-loaded `relevantAnswers`, no extra queries — so a
      result cache would trade that for a cache round-trip per formula, likely a net loss for typical formula
      counts, for meaningfully more complexity. Revisit only if profiling *after* this fix still shows
      `ApplyCalculation`'s `evaluate()` calls (not parsing) dominating for a configurator with many/expensive
      formulas.

- [x] **`CommitAnswers` does N individual inserts instead of a bulk insert.**
      Fixed 2026-08-27: `CommitAnswers` now builds one row array per pending answer and inserts them all via
      a single `Answer::query()->insert(...)` call, instead of looping `$chunk->answers()->create([...])`.
      Since a bulk `insert()` bypasses Eloquent's model events/casts, each row's `id` (UUID), `value`
      (JSON-encoded — the model's `array` cast only applies on the `create()`/read path, not `insert()`), and
      `created_at`/`updated_at` are now set explicitly in the mapped array rather than relying on `HasUuids`
      and the cast to populate them. `tests/Feature/Pipeline/PipelineQueryCountTest.php` gained a case
      asserting write-pipeline query count stays flat as the number of pending answers in one commit grows
      (was previously a documented, deliberate gap in that file); `tests/Feature/CommitConfigurationAnswersTest.php`
      gained a correctness case verifying every bulk-inserted `Answer` round-trips its `id`,
      `configuration_id`, `answer_chunk_id`, `value` (through the array cast on read), and timestamps.

- [x] **No query-count regression guard on the pipeline.**
      Fixed 2026-08-27: `tests/Feature/Pipeline/PipelineQueryCountTest.php` guards the full read pipeline
      (`FetchConfigurationState`) and the write pipeline (`CommitConfigurationAnswers`) against N+1
      regressions by comparing query counts between a small and a large scenario, rather than asserting a
      brittle absolute number — a regression is "this now scales with size," which a fixed-count assertion
      wouldn't distinguish from an unrelated incidental change. Covers: query count is flat as the number of
      fields in the current step grows, as the rest of the configurator tree grows, and as the number of
      already-answered steps (breadcrumbs) grows. Deliberately does *not* assert flatness for `CommitAnswers`
      across a growing number of *pending answers in one commit* — that's the still-open N-individual-inserts
      item above; the write-pipeline test here only varies unrelated tree size for a single answer, which is
      already flat today.

Found via re-profiling on 2026-08-27, after the five items above landed.

- [ ] **Chunked flow-rule scan can turn one query into several for a large, mostly-unanswered tree.**
      `ApplyFlow::findFlowTarget` (`app/Actions/Pipeline/ApplyFlow.php:97-102`) fetches
      `Rule::whereIn('view_id', chunk)` per 25-view chunk (`FLOW_RULE_CHUNK_SIZE`), stopping at the first
      firing rule. Worst case — a fresh Configuration on a large tree, nothing answered yet — is now
      `ceil(candidates / 25)` sequential queries, where the pre-fix code paid for this once via the
      already-eager-loaded `rules` on the single full-tree query. `PipelineQueryCountTest`'s "large tree"
      case only builds `extraStepCount: 15`, under the chunk size, so it doesn't exercise the multi-chunk
      path and wouldn't catch a regression here. Pick up when: profiling shows multiple `Rule` queries on a
      cold load of a configurator with >25 unanswered top-level steps, or add a test case that pushes past
      the chunk size to confirm the trade-off is acceptable as-is.

- [x] **Redis-backed expression parse cache has no in-request memoization.**
      Fixed 2026-08-27: `ExpressionLanguageServiceProvider` now wraps the `RedisAdapter` in a
      `Symfony\Component\Cache\Adapter\ChainAdapter` with an `ArrayAdapter` in front
      (`app/Providers/ExpressionLanguageServiceProvider.php`). `ChainAdapter::getItem()` checks the array
      adapter first and only falls through to Redis on a miss, syncing the result back into the array adapter
      — so within one request (there's no Octane; the `ExpressionLanguage` singleton is rebuilt per request),
      the first `parse()` of a given expression pays for the Redis round-trip and every repeat parse of that
      same expression is served from memory. `tests/Feature/ExpressionLanguageServiceProviderTest.php` asserts
      the cache is a `ChainAdapter` with an `ArrayAdapter` as its first member, and that parsing an expression
      once populates that array adapter under the same cache key `ExpressionLanguage::parse()` derives.

      Related, lower-priority: cached ASTs have no TTL (`RedisAdapter`'s `defaultLifetime` defaults to `0` —
      `ExpressionLanguageServiceProvider.php:19` doesn't pass one), and the `redis` service in `compose.yaml`
      has no memory limit or eviction policy. Every rule/formula/label edit mints a new cache key; old ones
      never expire. Not urgent, but worth a TTL or `maxmemory-policy` before this runs unattended in
      production for a long time.

## Data model (`app/Models/Component.php`)

- [ ] **Fold `Component` into `Configurator` — it's a generic `type => config` table that only ever holds two rows.**
      `components` has a `unique(['configurator_id', 'type'])` constraint and `ComponentType` has seven cases,
      but in practice only `ComponentType::Scope` (`config: {depth}`) and `ComponentType::Calculation`
      (`config: {formulas}`) rows are ever created — `ComponentFactory` only defines `scope()`/`calculation()`
      states, and every seeder (`RockBandGearConfiguratorSeeder`, `WidgetShowcaseSeeder`,
      `FictionalSurveySeeder`, `TemplatingShowcaseSeeder`, `WienerbergerWevoltSeeder`) and every test that
      creates a `Component` uses one of those two. The remaining cases (`Visibility`, `Validation`,
      `AnsweredViews`, `Breadcrumbs`, `PendingViews`) are never persisted — they're only used as string keys
      into the in-memory `ConfigurationEvaluation::$componentContexts` array set by pipeline stages
      (`ApplyFlow.php:66,71`, `ApplyBreadcrumbs.php:18`), unrelated to the DB table.

      Every DB read of `Component` today (`ApplyFlow.php:27`, `ApplyCalculation.php:21`,
      `CollectAndBatchFetchExternalReferences.php:26`) is already scoped to one configurator and one known
      type, so replacing the table with `scope_depth` (int) and `calculation_formulas` (json) columns directly
      on `configurators` collapses each of those into a plain attribute read — no query at all, since
      `configurator` is already eager-loaded onto `$evaluation->configuration` before the pipeline runs
      (`FetchConfigurationState.php:28`, `CommitConfigurationAnswers.php:31` both `loadMissing('configurator.rootView')`).
      Removes `app/Models/Component.php`, `database/factories/ComponentFactory.php`, the DB-backed half of
      `ComponentType`, and the `components` table (new drop migration, don't edit the create migration).
      Touches every seeder and test listed above.

      Open question for `calculation_formulas`: unlike `scope_depth` (a single scalar), a configurator's
      formulas are a *named list* (`{name: expression}`), which is a less natural fit for one JSON column than
      `scope_depth` is. Worth considering a dedicated `calculations` table (one row per formula: `configurator_id`,
      `name`, `expression`) instead of a JSON blob column — trades one extra join/eager-load for per-formula
      rows that are individually indexable, queryable, and diffable, at the cost of reintroducing a second
      table (i.e. partially undoing the "fold into `Configurator`" simplification this item is chasing). Decide
      json-column vs. own-table before implementing either half of this item.

## Expression evaluation (`app/Actions/Pipeline/ApplyCalculation.php`, `app/Actions/Pipeline/ResolveModelFunction.php`)

- [ ] **Move cycle detection out of the per-request evaluation pipeline into a one-time "prebake" step on the configurator.**
      Two independent runtime guards exist today: `ApplyCalculation::topologicalOrder` DFS-checks
      `calculation.<name>` formulas for circular dependencies on every evaluation (throws
      `RuntimeException`, tested in `tests/Feature/Pipeline/ApplyCalculationTest.php:65-78`), and
      `ResolveModelFunction`'s `__resolving` stack catches circular references through templating functions
      generally — e.g. a `label()` chain that loops back on itself (tested in
      `tests/Feature/Pipeline/ResolveTemplateStringsTest.php:144+`). Both re-derive the same answer (is this
      configurator's formula/reference graph acyclic?) on every single request, even though the graph only
      changes when the configurator's rules/formulas/labels are edited.
      Reason to remove: the runtime cost and code complexity of re-checking on every evaluation isn't wanted —
      cycle-freedom should instead be established once, in a "prebake" step that runs when a configurator is
      saved/published, not on the hot path. Open question to resolve before removing the runtime guards:
      where the prebake step lives, what it validates (formula graph only, or also the templating-function
      graph `ResolveModelFunction` currently guards), and what happens to a configurator that fails prebake
      (reject the save, or flag and allow with a warning). Until that lands, removing the runtime guards
      outright would turn a circular formula/reference into an infinite loop / stack overflow at evaluation
      time instead of a caught error.

- [ ] **Audit every pipeline stage for prebake potential, not just the two cycle-detection guards above.**
      `FetchConfigurationState` and `CommitConfigurationAnswers` both run the same eight/ten-stage pipeline on
      every request (`GuardAnchorOverride`, `ApplyFlow`, `CollectAndBatchFetchExternalReferences`,
      `ApplyCalculation`, `ApplyVisibility`, `ApplyValidation`, `ResolveTemplateStrings`, `ApplyBreadcrumbs`
      and, for commits, `GuardValidationFailure`, `SupersedeAnswerChunks`, `CommitAnswers` — all in
      `app/Actions/Pipeline/`). The cycle-detection item above was found by looking for one specific pattern
      (repeated-but-static graph checks); no one has gone stage by stage asking the general question. For each
      stage, work out: what scope it actually needs (whole tree vs. current branch vs. single view — c.f. the
      `ApplyFlow`/`CollectAndBatchFetchExternalReferences` fixes in the "Pipeline performance" section above,
      which narrowed scope for two stages already), which of its calls are resource-heavy (DB queries, full
      collection scans over the tree), how much of its logic is inherent per-request work (depends on the
      answers given so far) vs. static per-configurator work that only changes when the configurator is
      edited, and — for the static part — whether it's a prebake candidate. Output should be one write-up per
      stage (or a "nothing to do here" note) added to this file, not a single blanket rewrite; some stages
      (e.g. anything reading `relevantAnswers`) are inherently per-request and won't have a prebake half.

- [ ] **Rework what goes into the `ExpressionLanguage` evaluation context — always-included vs. cheap-to-include vs. lazy, plus a prebake-backed cache.**
      Today `CollectAndBatchFetchExternalReferences.php:57-61` builds the *entire* context up front, every
      request, regardless of which specific expression ends up being evaluated: it regex-scans every scope
      view's templatable strings and rule expressions plus every calculation formula (`scanReferences`,
      `referencesThis`) to find every possibly-referenced view id, batch-fetches all of them
      (`$modelsById`), and stuffs in `calculationFormulas`, `relevantAnswers`, `relevantAnswerChunks`, and
      `pendingAnswers`. `ApplyCalculation.php:19-24` similarly evaluates *every* formula
      (`resolve()`/`topologicalOrder()`) every request, not just the ones a given `label()`/rule/validation
      actually references. Nothing about this is lazy today — the eager batch-fetch already replaced N+1
      per-reference queries with one upfront pass (see the "Pipeline performance" items above), but it still
      does that pass unconditionally rather than only for what's actually needed this evaluation.

      Work needed: go through every context key (`modelsById`, `calculationFormulas`, `calculation`,
      `relevantAnswers`, `relevantAnswerChunks`, `pendingAnswers`, `this`, `__resolving`, `__persistedOnly`)
      and classify it — always needed (cheap and used by nearly every expression, e.g. `relevantAnswers`),
      cheap enough to always include even if unused this evaluation (small, already in memory), or a genuine
      lazy-load candidate (expensive and only sometimes referenced, e.g. `modelsById` external views,
      individual formula results) that should resolve on first access from the expression instead of
      being precomputed for the whole scope. `ResolveModelFunction`'s per-call resolution
      (`app/Actions/Pipeline/ResolveModelFunction.php`) is already lazy in shape — it resolves one
      `modelClass:id:function` at a time — so laziness for `modelsById` may be a matter of deferring the
      batch-fetch until first access rather than restructuring the resolution path.

      Then: since the referenced-id graph (`scanReferences`/`referencesThis`'s output) and the formula
      dependency graph (`ApplyCalculation::topologicalOrder`) are both static per-configurator — they only
      depend on rule/template/formula text, never on answers — both are candidates to compute once during the
      "prebake" step from the cycle-detection item above and cache the result, instead of re-deriving them via
      regex/DFS on every request. Cache invalidation needs a lookup generated at prebake time (e.g. a
      content-hash or version stamp of the configurator's rules/views/formulas) so an edit reliably busts the
      cache — same shape of problem the parse-cache TTL note in the "Pipeline performance" section above is
      still open on, worth solving once for both.

## Testing (`tests/`)

- [ ] **Add a Pest architecture test suite — some prose conventions in `.ai/rules`/Boost guidelines are mechanically enforceable and should stop relying on an agent reading the rule file.**
      `.ai/rules` currently documents several structural conventions as prose that a Pest `arch()` expectation
      could check directly instead, e.g. after each edit:
      - `livewire.md` — "No direct DB queries in Livewire components": `arch()->expect('App\Livewire')->not->toUse(['Illuminate\Database\Eloquent\Builder', 'Illuminate\Support\Facades\DB'])` (or similar) catches a component reaching for a query directly instead of going through an Action/Query/pipeline `componentContexts`.
      - `app-actions.md` — "DTOs live in `app/Actions/DTO/`": every class directly under `App\Actions` (excluding `App\Actions\DTO` and `App\Actions\Pipeline`) should have an `__invoke()` method; every class under `App\Actions\DTO` should not.
      - `actions.md` — single-purpose Actions have exactly one public method, `__invoke()`, verb-first name — arch can check "has `__invoke`"; check what Pest 5's arch API can express for "only one public method" and naming pattern, since that part may need a custom expectation instead of a built-in one.
      - `models.md` — "Domain models use `HasUuids`": `arch()->expect('App\Models')->toUse(Illuminate\Database\Eloquent\Concerns\HasUuids::class)`, scoped to exclude any framework model that keeps auto-increment ids (none currently, but don't assume that stays true).
      - `rules-engine.md` — "an Action never belongs in `Pipeline/` unless something else's pipeline invokes it" and the two pipelines' stage lists themselves are candidates for a narrower check (e.g. every class in `App\Actions\Pipeline` is only referenced from `FetchConfigurationState`/`CommitConfigurationAnswers`'s `->through([...])` arrays) — likely needs a custom arch expectation or a plain reflection-based test, not a stock `toUse`/`toOnlyBeUsedIn` one; check what's available before assuming it's expressible.
      - `app.md` — "prefer global helpers over facades/`new`'d classes" is a weaker candidate: broadly true but has legitimate exceptions (see the rule's own carve-out), so a blanket `not->toUse` would false-positive; skip unless a narrower, exception-free version of the rule can be stated.

      Where an arch test can express a rule exactly, add the test and shrink the corresponding `.ai/rules` prose
      to point at it ("enforced by `tests/Arch/...`") instead of restating the constraint, so the two can't
      silently drift — the test is the enforcement, the rule file stays for the *why* an agent still needs to
      know. Where a rule turns out not to be cleanly expressible (mixed-in exceptions, needs call-site/usage
      analysis rather than class metadata), leave it as prose and note in this TODO why it didn't fit rather
      than forcing a brittle approximation. Check `pestphp/pest-plugin-arch`'s actual capabilities in this
      project's installed Pest version (`composer show pestphp/pest` — CLAUDE.md's Foundational Context: don't
      assume API surface from a different major version) before committing to specific expectations above.
