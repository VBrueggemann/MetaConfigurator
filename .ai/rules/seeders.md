---
paths:
  - 'database/seeders/**'
---

# Seeders

## Scope depth toggles single-page vs multi-step on the same tree
A seeder that wants a togglable single-page/multi-step demo should nest one extra Group level (root → shell → allQuestions → steps...) and expose a `depth` constructor param that it writes onto the configurator's `scope_depth` column (`$configurator->update(['scope_depth' => $depth])`) — depth pointing at the shallow wrapper puts everything on one page, depth pointing at the step level makes each step its own page, with no other changes to the tree. See the rules-engine.md note on giving every step an explicit Flow rule for why linear multi-step wizards can't rely on ApplyFlow's fallback.
