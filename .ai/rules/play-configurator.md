---
paths:
  - 'resources/views/livewire/play-configurator/**'
---

# Play Configurator

## Group layout direction is config-driven via config['direction']
A plain Group (no `widget`) renders its children inside a wrapper `<div class="flex ...">` in item.blade.php. `config['direction']` selects the layout: `'horizontal'` → `flex-row flex-wrap gap-4`; anything else (including absent, the default) → `flex-col space-y-6`. Read directly in the Blade partial only — PlayConfigurator/backend stays agnostic to this value, same as `widget`. `ViewFactory::group(?label, ?direction)` accepts the second param for tests.

## Input sync timing is config-driven via config['updateOn']
Every Input widget partial (`text`, `number`, `email`, `date`, `color`, `range`, `checkbox`, `radio`, `select`, `datalist`) reads `config['updateOn']` to pick its `wire:model` modifier: `'live'` → `wire:model.live` (syncs and hits the server on every keystroke/change); anything else (including absent, the default) → `wire:model.live.blur` (only syncs once the field is blurred — the pre-existing behavior, kept as default so unconfigured fields don't change). Read directly in each widget partial, same as `widget`/`direction` — PlayConfigurator/backend stays agnostic. The multiselect group widget (`widgets/group/multiselect.blade.php`) is unaffected — it always uses `wire:model.live` regardless of this config. `ViewFactory::updateOn(string $updateOn)` is a chainable state modifier (e.g. `View::factory()->textInput()->updateOn('live')`) rather than a param on each widget factory method, since it applies uniformly across all Input widget factories.
