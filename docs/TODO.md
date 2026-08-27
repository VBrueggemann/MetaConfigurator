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

- [ ] **`CommitAnswers` does N individual inserts instead of a bulk insert.**
      `app/Actions/Pipeline/CommitAnswers.php:22` — one `create()` call per pending answer inside the
      transaction, plus a separate `max('sequence')` query and a chunk `create()`. Fine for single-field
      steps; worth batching (`insert()`) if/when a step type with many fields per submission (e.g. a
      matrix/group step) becomes common.

- [ ] **No query-count regression guard on the pipeline.**
      Only `tests/Feature/Pipeline/CollectAndBatchFetchExternalReferencesTest.php` asserts query counts.
      Nothing catches an N+1 regression in `ApplyFlow` or the full pipeline. Add an `assertQueryCount`-style
      test once the above items are addressed, so the fix doesn't silently regress.
