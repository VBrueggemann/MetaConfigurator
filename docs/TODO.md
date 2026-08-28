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

- [x] **Fold `Component` into `Configurator` — it's a generic `type => config` table that only ever holds two rows.**
      Fixed 2026-08-28: `app/Models/Component.php`, `app/Enums/ComponentType.php`, `database/factories/ComponentFactory.php`,
      and the `components` table (dropped via a new migration, per this repo's create-migrations-are-immutable
      convention) are gone. `scope_depth` (unsigned int, default `0`) is now a plain column on `configurators`
      — confirmed via `ComponentFactory::scope()`'s unused `$extra` param that no seeder ever needed more than
      the depth scalar. Calculation formulas became their own `Calculation` model/table (`configurator_id`,
      `name`, `text` `expression`, `unique(configurator_id, name)`) rather than a JSON blob column on
      `Configurator` — a deliberate choice to keep formulas individually diffable/queryable, even though it
      re-adds a second table, because a per-formula-list JSON column was judged the wrong trade-off here.
      `expression` had to be `text`, not `string(255)` — one real seeded formula
      (`RockBandGearConfiguratorSeeder`'s `guitarBrandPrice`, a nested ternary) is over 255 characters and
      failed to insert under the initial `string` choice. `ApplyFlow`, `ApplyCalculation`, and
      `CollectAndBatchFetchExternalReferences` now read `$configurator->scope_depth` /
      `$configurator->calculations` directly (both eager-loaded via `loadMissing(['configurator.rootView',
      'configurator.calculations'])` in `FetchConfigurationState`/`CommitConfigurationAnswers`) instead of
      querying `components`. The remaining `ComponentType` cases (`Visibility`, `Validation`, `AnsweredViews`,
      `Breadcrumbs`, `PendingViews`) were never DB-persisted (confirmed by audit — `Visibility` was only ever
      the factory's unused default state, `Validation`/`AnsweredViews` had zero references anywhere) and are
      now plain string literals (`'scope'`, `'pending_views'`, `'breadcrumbs'`) wherever they were previously
      used as `componentContexts` array keys, since there's no shared enum left to hang them on and no roadmap
      for a generic pipeline-stage-to-DB-entity coupling. All five seeders and every affected test updated;
      added a `seedCalculations()` test helper in `tests/Pest.php` for the seed-then-assert pattern
      `ApplyCalculationTest`/`ResolveTemplateStringsTest` both need.

- [x] **Extract a `Template` model for the four `{{ }}`-interpolated string fields — prerequisite for the cycle-detection item below.**
      Fixed 2026-08-28: added `Template` (`app/Models/Template.php`, `templates` table — uuid pk, standard
      `uuidMorphs('templatable')` columns, `key`, `template` text, nullable `order` int, timestamps), with the
      inverse `morphMany` added to `View` and `Rule` only — not `Configurator`, which has no templatable field
      today. `key` discriminates `label`/`content`/`option_label`/`message`; `order` is null for the first three
      (one row per View/Rule) and holds the option's array index for `option_label` (a View can have several,
      one per `config['options']` entry) — `config['options']` itself now stores everything *except* `label`
      (`value`/`price`/`icon`/`subtitle` etc. stay put), with the label reconstructed back onto
      `config['options'][$index]['label']` at resolve time.

      Did **not** absorb `Rule.expression` or `Calculation.expression`, per the original plan — those stay raw
      ExpressionLanguage code, untouched.

      Every read/write site now goes through `Template`: `config/templating.php`'s `functions.View.label`
      resolver and the `resolvable` list (Rule/message, View/label+content+option_label) source raw text from
      `->templates` (grouped by `key`) but still write the *resolved* output back onto the same in-memory
      `rule.meta['message']`/`view.config['label']`/`['content']`/`['options'][].label` keys as before — this
      preserves `ResolveTemplateStrings`'s existing mutation contract (mutate in memory, never persist) and
      means `resources/views/livewire/play-configurator/**` needed zero changes, since Blade already only ever
      read those same keys off the pipeline-resolved tree. `CollectAndBatchFetchExternalReferences::templatableStrings()`
      now scans `$view->templates`/`$rule->templates` instead of the old JSON locations. `ApplyFlow`'s blanket
      scope-tree eager-load gained `bloodline.templates`/`bloodline.rules.templates` (same reason `bloodline.rules`
      was already there) so the scan stays N+1-free; `PipelineQueryCountTest` confirms no regression.

      Two read paths outside the main pipeline also needed the relation: `ApplyBreadcrumbs` (breadcrumb view
      labels) and `BuildConfiguratorAnalytics`/`ConfiguratorAnalyticsPage::formatValue()` (step/answer/transition
      labels and option-value-to-label lookups for the analytics page) — both now eager-load `templates` and
      the two `configurator-analytics.blade.php`/`play-configurator.blade.php` breadcrumb spots read the label
      off `->templates->firstWhere('key', 'label')?->template` instead of `->config['label']`, since those views
      are fetched outside `ResolveTemplateStrings`'s scope-tree walk and never get a resolved `config['label']`
      written back.

      `ViewFactory`'s `label`/`content`/`options` convenience params and a new `RuleFactory::message()` state
      now create `Template` rows via `afterCreating()` instead of writing into `config`/`meta` directly, so
      every seeder and test that already used those factory params kept working unchanged; only call sites that
      set `config`/`meta` raw (a few `->update(['config' => ['content' => ...]])` calls, inline
      `'meta' => ['message' => ...]` arrays) needed converting across all 5 seeders and the pipeline test suite.
      A new `template()` Pest helper (`tests/Pest.php`) mirrors `seedCalculations()` for the remaining
      hand-written cases (e.g. the circular-label-reference test).

## Expression evaluation (`app/Actions/Pipeline/ApplyCalculation.php`, `app/Actions/Pipeline/ResolveModelFunction.php`)

- [x] **Move cycle detection out of the per-request evaluation pipeline into a one-time bake step on the configurator.**
      Fixed 2026-08-28: added a `graphs_acyclic` boolean on `Configurator` (default `false`) and a new
      `BakeConfiguratorGraphs` pipeline stage (`app/Actions/Pipeline/BakeConfiguratorGraphs.php`) that runs first
      in both `FetchConfigurationState` and `CommitConfigurationAnswers`. While the flag is `false` it builds two
      graphs — the `Calculation` dependency graph (same `calculation.<name>` regex `ApplyCalculation` already
      used) and a `View` label-reference graph — asserts both acyclic (`RuntimeException` on a real cycle), then
      persists `graphs_acyclic = true`; once `true` it's a single already-loaded-attribute check, no graph walk.
      `ResolveModelFunction`'s `__resolving` stack is gone outright.

      Extended same-day: the calculation *topological order* itself — which formula must evaluate before which —
      is exactly as static per-configurator as the acyclic check, and computing it was already a byproduct of the
      same cycle-detecting DFS, so `BakeConfiguratorGraphs` now returns and persists it too, as a new
      `calculation_order` json column on `Configurator`, in the same `update()` call as `graphs_acyclic`.
      `ApplyCalculation::topologicalOrder` is gone entirely — `ApplyCalculation::resolve()` just iterates
      `$configurator->calculation_order` instead of re-deriving the order from formula text every request. This
      relies on `BakeConfiguratorGraphs` always running before `ApplyCalculation` in the same pipeline (true in
      both `FetchConfigurationState`/`CommitConfigurationAnswers`) and always setting both columns together — a
      test that manually pre-bakes a configurator (`PipelineQueryCountTest`'s shared-configurator scenario) has to
      set `calculation_order` alongside `graphs_acyclic` for the same reason.

      Narrower graph than originally planned: turns out `label()` is the *only* templating function whose
      resolver recurses (it calls `ResolveTemplate` again on the target's own `label` template) —
      `answer()`/`validationError()` just read already-resolved data and never re-enter template resolution. So
      a genuine cycle can only ever form through `label()`-to-`label()` chains; `content`/`message`/`option_label`
      templates can *call into* that graph but are never themselves a re-entry target (there's no `content()`
      function), so they can't participate in an actual cycle. The bake step's label graph reflects this: nodes
      are views with a `label` `Template`, edges are literal `label('id')` occurrences inside that template text
      only — not a scan of all four templatable fields.

      Also tightened, deliberately, after being flagged and confirmed by the user: a circular **label** reference
      used to degrade gracefully (`ResolveTemplate`'s own `catch (Throwable)` swallowed the old
      `ResolveModelFunction` exception and rendered the raw `{{ label(...) }}` text, rest of the page still
      worked). It's now a hard bake-time failure, same as a circular **calculation** dependency always was — the
      whole evaluation throws instead of one spot looking broken. Chosen over keeping it soft (which would've
      meant leaving `ResolveModelFunction`'s guard in place just for `label()`) because a circular label reference
      is always an authoring bug, never legitimate, and failing loudly once beats silently shipping broken text
      indefinitely. `tests/Feature/Pipeline/ResolveTemplateStringsTest.php`'s circular-label test now asserts
      `toThrow(...)` instead of the old fallback-string assertion; `tests/Feature/Pipeline/BakeConfiguratorGraphsTest.php`
      covers the stage directly (bakes to `true` on a clean configurator, is a zero-query no-op once already
      `true`, throws on each kind of cycle without persisting `true`).

      `tests/Feature/Pipeline/PipelineQueryCountTest.php`'s "already-answered steps" scenario needed a
      `'graphs_acyclic' => true` on its shared `Configurator` — it reuses one configurator for two `Configuration`s
      and compares query counts between them, so without pre-baking, the first of the two calls silently absorbed
      the one-time bake cost and the second didn't, making the two counts spuriously unequal. Not a real scaling
      regression, just that test's setup needing to reflect steady-state (post-bake) cost rather than first-ever-request cost.

- [x] **Audit every pipeline stage for prebake potential, not just the two cycle-detection guards above.**
      Audited 2026-08-28, no code changes — every remaining stage's per-request cost is either already a
      single batched query, pure in-memory computation over already-loaded data, an actual write of new
      state, or inherently answer-dependent (can't be known ahead of the current request's `relevantAnswers`/
      `pendingAnswers`). One write-up per stage, in pipeline order:

      - **`GuardAnchorOverride`** — pure in-memory `contains()` check against the already-loaded
        `relevantAnswerChunks` relation, zero queries. What it checks (does `anchorViewId` name an existing
        relevant chunk anchor *for this Configuration*) is per-session state, not a per-configurator-static
        fact — nothing to bake.
      - **`ApplyFlow`** — two parts. The whole-tree `$rootView->descendantsAndSelf()->depthFirst()->get()` is
        structurally static per configurator (only changes when the configurator itself is edited), but
        `pipeline.md`'s "`path`/`depth` are CTE-only, never materialized columns" constraint means there is no
        way to serve a depth-first-ordered view list without going through that recursive CTE — baking it
        would mean caching the query *result*, which still needs a cache-invalidation-on-edit story most other
        bakes here don't (they invalidate via a boolean/json column set once and never touched again outside a
        migration). Not picked up here; the real prebake-enabling change would be materializing `path`/`depth`
        columns, which is a schema change out of scope for an audit. The flow-rule scan itself
        (`findFlowTarget`) is inherently per-request — which rule fires depends on `answeredViewIds`, i.e. the
        answers given so far — and is already chunked/short-circuiting (see the still-open "Chunked flow-rule
        scan" item above).
      - **`CollectAndBatchFetchExternalReferences`** — already investigated and closed above (the
        "Rework what goes into the `ExpressionLanguage` evaluation context" item, 2026-08-28): the regex scan
        runs over already-loaded, already-small in-scope text, and the external-view fetch is already one
        batched `whereIn()`. Nothing new found here.
      - **`ApplyCalculation`** — cycle-freedom and topological order are already baked
        (`BakeConfiguratorGraphs`, above). The remaining cost, `evaluate()` per formula against the current
        `calculation` context, is answer-dependent by construction — a formula's *result* changes every time
        an upstream answer changes, so there's no static remainder left to bake. A calculation-result cache
        was considered and rejected in the same investigated item above (net loss given the parse cache already
        makes `evaluate()` a cheap in-process tree-walk).
      - **`ApplyVisibility`** — Visibility rule expressions read `answer(id)`, so a view's visibility is
        answer-dependent by construction; there's no configurator-static visibility fact to bake. Already scoped
        to the current bloodline only (not a full-tree query) per the "`ApplyVisibility` computes from the scope
        tree" note in `pipeline.md`.
      - **`ApplyValidation`** — same shape as Visibility: which Validation rules pass/fail reads `answer(id)`,
        and which views are "pending" comes from this request's `pendingAnswers`. Both are inherently
        per-request; nothing static to extract.
      - **`ResolveTemplateStrings`/`ResolveTemplate`** — every resolvable field is a `{{ }}` template whose
        only registered functions (`answer`/`label`/`validationError`) are answer- or validation-state-
        dependent, so the resolved *string* is inherently per-request. The one static, repeated-across-requests
        cost here — parsing the same expression AST — is already cached (the Redis + in-request `ArrayAdapter`
        chain from the "no persistent parse cache" / "no in-request memoization" items above). No further static
        remainder.
      - **`ApplyBreadcrumbs`** — one `View::whereIn($chunks->pluck('anchor_view_id'))->with('templates')->get()`
        per read-pipeline request. The *id set* (which views are breadcrumb anchors) is inherently per-Configuration
        state that grows over the session, not a per-configurator-static fact, so there's nothing to bake there.
        The View rows/templates it fetches are configurator-static, but the set is small (one per step actually
        visited so far) and already a single batched query — same shape already accepted for the external-view
        fetch in `CollectAndBatchFetchExternalReferences`. Not worth restructuring for a query this cheap.
      - **`GuardValidationFailure`** (commit only) — pure in-memory scan of the already-loaded
        `componentContexts['scope']`, zero queries.
      - **`SupersedeAnswerChunks`**/**`CommitAnswers`** (commit only) — both are writes of this request's actual
        new state (superseding old chunks, inserting this request's answers). A write can't be precomputed ahead
        of the request that produces the data it writes — not applicable to prebaking at all, by definition.

      Net finding: the two cycle-detection/topological-order bakes already landed were the only genuinely
      static, repeated-per-request, per-configurator facts in this pipeline. Everything else is either already
      a single batched query/in-memory op, or is answer-dependent and therefore has no prebake half — consistent
      with how the item was scoped going in.

- [x] **Rework what goes into the `ExpressionLanguage` evaluation context — always-included vs. cheap-to-include vs. lazy, plus a prebake-backed cache.**
      Investigated via grilling on 2026-08-28, decided against — closing without code changes. This item was
      written before `BakeConfiguratorGraphs` existed; two of the things it wanted "prebaked" (the formula
      dependency graph's cycle-freedom and its topological order) already landed as part of that stage (see the
      cycle-detection item above), just framed as "cycle detection + evaluation order" rather than "lazy
      context." Re-examined what's actually left in `CollectAndBatchFetchExternalReferences`/`ApplyCalculation`'s
      context keys with that already accounted for, and none of the remaining pieces hold up:

      - `calculationFormulas`, `relevantAnswers`, `relevantAnswerChunks`, `pendingAnswers` already cost zero
        extra queries — each is plucked straight off a relation already eager-loaded before the pipeline even
        starts (`configurator.calculations`, `relevantAnswers`/`relevantAnswerChunks` via
        `$configuration->load([...])` at the top of `FetchConfigurationState`/`CommitConfigurationAnswers`).
        There's no query to defer; it's already just handing off an in-memory collection.
      - The reference-id regex scan (`scanReferences`/`referencesThis` over `templatableStrings()`) runs over
        the current **scope** only (the anchor's bloodline), not the whole configurator, over text that's
        already in memory via `ApplyFlow`'s eager-load — pure CPU over an already-small, already-loaded string
        set, no DB query to cache away. Unlike the label-cycle graph, which is a global, anchor-independent fact
        about the whole configurator (hence baking it once per configurator made sense), *which ids get
        referenced* changes with which step you're on — caching it the same way would mean one cache entry per
        possible anchor view, not one per configurator. Doesn't fit the pattern `BakeConfiguratorGraphs`
        established.
      - `modelsById`'s external-view fetch — the one real DB cost here — is already a single batched
        `whereIn()` query, not N+1 (that's what the "Pipeline performance" items above already fixed).
        Deferring it to a genuinely lazy per-id fetch wouldn't reduce query count in the common case, and risks
        *reintroducing* N+1 if several different external ids end up actually used within one request (each
        needing its own fetch instead of one batch). The only thing true laziness would save is hydrating a few
        unused external `View` rows — less data, not fewer queries — and `PipelineQueryCountTest` already proves
        query count stays flat regardless of tree size or field count today.

      Revisit only if profiling shows the external-view batch fetch hydrating large unused `config`/`Template`
      payloads for a configurator with many external references — a real, falsifiable trigger, not a design to
      build speculatively now.

## Testing (`tests/`)

- [x] **Add a Pest architecture test suite — some prose conventions in `.ai/rules`/Boost guidelines are mechanically enforceable and should stop relying on an agent reading the rule file.**
      Done for the three rules that turned out cleanly expressible against the installed `pestphp/pest` v5.1.1 /
      `pest-plugin-arch` v5.0.0, each verified to actually fail on a real violation (not just pass vacuously)
      before being kept:
      - `tests/Arch/LivewireTest.php` — livewire.md's no-direct-DB-queries rule.
      - `tests/Arch/ActionsTest.php` — app-actions.md's DTO/`__invoke()` split.
      - `tests/Arch/ModelsTest.php` — models.md's `HasUuids` rule. Note the trap here: the obvious
        `expect('App\Models')->toUse(HasUuids::class)` is a namespace-*aggregate* check (passes the moment
        *any* model in the namespace uses the trait) — silently stops enforcing anything once one real model
        has it. `->toUseTrait(...)` is the actual per-class check; see the rule file for the full explanation.
      Each corresponding `.ai/rules` file now points at its enforcing test instead of restating the constraint.

      Left as prose, not mechanically enforced:
      - `actions.md`'s "exactly one public method" and verb-first naming — `toHavePublicMethods()`/
        `toHavePublicMethodsBesides()` are unimplemented stubs in this Pest version (throw
        `InvalidExpectation`), and there's no naming-semantics matcher for "verb-first". Re-check if the
        installed version changes.
      - `rules-engine.md`'s "an Action never belongs in `Pipeline/` unless something else's pipeline invokes
        it" — every `tests/Feature/Pipeline/*Test.php` also constructs a stage directly
        (`app(ApplyFlow::class)(...)`), and stages inject each other directly too (`ApplyValidation` takes
        `EvaluateRule` in its constructor), so a `toOnlyBeUsedIn`/`toBeUsedIn` check would either false-positive
        on that legitimate usage or require distinguishing a `->through()` array literal from constructor
        injection, which isn't expressible with this plugin version's usage-based expectations.
      - `app.md`'s "prefer global helpers over facades/`new`'d classes" — has legitimate exceptions per the
        rule's own carve-out, so a blanket `not->toUse` would false-positive; skip unless a narrower,
        exception-free version of the rule can be stated.
