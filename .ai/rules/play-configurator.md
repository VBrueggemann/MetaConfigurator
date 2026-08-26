---
paths:
  - 'resources/views/livewire/play-configurator/**'
---

# Play Configurator

## Group layout direction is config-driven via config['direction']
A plain Group (no `widget`) renders its children inside a wrapper `<div class="flex ...">` in item.blade.php. `config['direction']` selects the layout: `'horizontal'` → `flex-row flex-wrap gap-4`; anything else (including absent, the default) → `flex-col space-y-6`. Read directly in the Blade partial only — PlayConfigurator/backend stays agnostic to this value, same as `widget`. `ViewFactory::group(?label, ?direction)` accepts the second param for tests.
