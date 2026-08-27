# TODO

Backlog of known-but-not-yet-scheduled work. Each item names the trigger for picking it up.

## Pipeline performance (`app/Actions/Pipeline/**`)

Found via profiling on 2026-08-27. Context: `FetchConfigurationState`/`CommitConfigurationAnswers`
run the full stage pipeline on every navigation *and* on every `wire:model.live` field change
(default for most widgets unless a view's `config['updateOn']` is `blur`) — see
`app/Livewire/PlayConfigurator.php`'s `updatedValues()`.

- [ ] **`ApplyFlow` loads the entire configurator tree on every request, not just the current scope.**
      `app/Actions/Pipeline/ApplyFlow.php:31` — `$rootView->descendantsAndSelf()->with([...$with, 'rules'])->depthFirst()->get()`
      fetches every View in the whole configurator (plus eager-loaded relations) on every read/write,
      including live-keystroke updates. Cost scales with total configurator size, not current-step size.
      Pick up when: a configurator with a large view tree shows slow field updates, or before onboarding
      a customer with a big Configurator.

- [ ] **`answer()`'s `descendants` eager load is applied to every View, not just Groups.**
      `config/templating.php:20` registers `'with' => ['descendants']` for the `answer` function, and
      `ApplyFlow` unions `$with` globally across all registered functions (`app/Actions/Pipeline/ApplyFlow.php:30`)
      instead of per-view-type. Every leaf (non-Group) View eager-loads a `descendants` relation it never
      uses; every Group node materializes a hydrated copy of its full subtree. Fix likely requires scoping
      `$with` by `View::type` rather than unioning across the whole function registry. Pick up alongside the
      `ApplyFlow` full-tree item above — same root cause.

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
