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

- [ ] **Redis-backed expression parse cache has no in-request memoization.**
      `ExpressionLanguage::parse()` calls `$this->cache->getItem(...)` unconditionally
      (`vendor/symfony/expression-language/ExpressionLanguage.php:80`) — no short-circuit before the cache
      lookup. Since `ExpressionLanguageServiceProvider` now backs that cache with `RedisAdapter`
      (`app/Providers/ExpressionLanguageServiceProvider.php:19-21`), every `evaluate()` call is a real Redis
      round-trip, not just a re-parse. A scope with ~20 rules/labels/formulas means ~20+ sequential Redis GETs
      per pipeline run, on every `wire:model.live` keystroke. Locally sub-ms per call, but worth checking
      under real latency. Fix: stack an in-memory `ArrayAdapter` in front via
      `Symfony\Component\Cache\Adapter\ChainAdapter` so repeated evaluations within one request skip Redis
      entirely. Pick up when: profiling shows Redis round-trip time dominating a request, or opportunistically
      alongside other `ExpressionLanguage` work.

      Related, lower-priority: cached ASTs have no TTL (`RedisAdapter`'s `defaultLifetime` defaults to `0` —
      `ExpressionLanguageServiceProvider.php:19` doesn't pass one), and the `redis` service in `compose.yaml`
      has no memory limit or eviction policy. Every rule/formula/label edit mints a new cache key; old ones
      never expire. Not urgent, but worth a TTL or `maxmemory-policy` before this runs unattended in
      production for a long time.
