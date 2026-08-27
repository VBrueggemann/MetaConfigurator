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

- [ ] **`ExpressionLanguage` has no persistent parse cache.**
      `app/Providers/ExpressionLanguageServiceProvider.php:16` — `new ExpressionLanguage` with no PSR-6
      cache adapter. Rule/formula/template expressions are static per configurator version but get
      re-parsed from source on every request (no cache survives past the current PHP-FPM process). Wire
      up a persistent adapter (Redis/file) keyed on expression + variable names. Pick up when profiling
      shows CPU time in `symfony/expression-language` parsing, or opportunistically alongside the
      `ApplyFlow` work since both are hit on every pipeline run.

- [ ] **`CommitAnswers` does N individual inserts instead of a bulk insert.**
      `app/Actions/Pipeline/CommitAnswers.php:22` — one `create()` call per pending answer inside the
      transaction, plus a separate `max('sequence')` query and a chunk `create()`. Fine for single-field
      steps; worth batching (`insert()`) if/when a step type with many fields per submission (e.g. a
      matrix/group step) becomes common.

- [ ] **No query-count regression guard on the pipeline.**
      Only `tests/Feature/Pipeline/CollectAndBatchFetchExternalReferencesTest.php` asserts query counts.
      Nothing catches an N+1 regression in `ApplyFlow` or the full pipeline. Add an `assertQueryCount`-style
      test once the above items are addressed, so the fix doesn't silently regress.
