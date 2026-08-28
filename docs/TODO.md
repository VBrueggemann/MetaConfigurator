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

- [ ] **Move cycle detection out of the per-request evaluation pipeline into a one-time bake step on the configurator.**
      Two independent runtime guards exist today: `ApplyCalculation::topologicalOrder` DFS-checks
      `calculation.<name>` formulas for circular dependencies on every evaluation (throws
      `RuntimeException`, tested in `tests/Feature/Pipeline/ApplyCalculationTest.php:65-78`), and
      `ResolveModelFunction`'s `__resolving` stack catches circular references through templating functions
      generally — e.g. a `label()` chain that loops back on itself (tested in
      `tests/Feature/Pipeline/ResolveTemplateStringsTest.php:144+`). Both re-derive the same answer (is this
      configurator's formula/reference graph acyclic?) on every single request, even though the graph only
      changes when the configurator's rules/formulas/labels are edited.

      Design settled via grilling on 2026-08-28 (depends on the `Template` model item above landing first, since
      this reads `Template` + `Calculation` rather than walking `View.config`/`Rule.meta` JSON):

      - **Drop both runtime guards, not just the calculation one.** Checked whether removing
        `ResolveModelFunction`'s guard is safe given `CollectAndBatchFetchExternalReferences::scanReferences`'s
        regex only matches a quoted-literal argument (`/\b(fn)\(\s*['"]([^'"]+)['"]\s*\)/`) — a
        dynamically-computed id (`answer(otherAnswer())`) is already invisible to that scan today, so it never
        lands in `modelsById`, so `ResolveModelFunction` already throws "Unknown reference" before a cycle could
        form. Every cycle that can actually occur through `answer()`/`label()`/`validationError()` is built from
        literal ids, which a static scan finds completely — no blind spot, safe to remove both guards.

      - **One boolean on `Configurator`** (e.g. `graphs_acyclic`), covering both graphs — the `Calculation`
        dependency graph and the `Template`/`Rule` literal-id reference graph — not one flag per graph. A single
        flag stays simpler and loses nothing for debugging, since the exception thrown on a real cycle already
        names the offending key/id (same as today, just relocated to bake time).

      - **Defaults to `false`.** There's no configurator-builder/editor UI yet (only the 5 seeders create
        `Configurator`/`Template`/`Calculation` rows), so nothing bakes the graph at "save" time today. Instead: a
        new early pipeline stage checks the flag; if `false`, it builds both graphs, throws the same
        `RuntimeException` as today if either is cyclic, otherwise persists `graphs_acyclic = true` on the
        `Configurator` and continues. If `true`, it's a single already-loaded-attribute check, no graph walk. So
        today the first evaluation of a freshly-seeded configurator pays for one bake; every request after is
        O(1). Once a real editor exists, its save action becomes responsible for baking eagerly at save time
        instead — a configurator then never reaches evaluation un-baked at all, and this stage's `false` branch
        becomes dead code for anything saved through the editor.

      - **On a cycle: throw**, same as today (`RuntimeException`) — not flag-and-allow. Every existing test
        already expects a hard failure on a cyclic graph, so this is the smallest possible behavioral diff from
        today. Revisit flag-and-allow (degrading via `ApplyCalculation::resolve`'s existing
        `catch (Throwable)`-and-fallback path) or failing at the `PlayConfigurator` UI boundary instead, once
        there's an actual editor UI where surfacing "this configurator is broken" to its author is a reachable
        moment, rather than a dev-only seeder mistake.

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
