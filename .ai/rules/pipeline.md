---
paths:
  - 'app/Actions/Pipeline/**'
---

# Pipeline

## String templating via {{ }} substitution, not inline expressions
`View.config['label']`/`config['content']`/`config['options'][].label` and `Rule.meta['message']` support `{{ expression }}` substitution, resolved by `ResolveTemplate` (never inline arithmetic — real math stays in the `Calculation` component; a template can only reference `answers[id]`/`calculation.name`/`label(id)`/`message(id)`).

`label(id)`/`message(id)` are ExpressionLanguage functions (registered in `ExpressionLanguageServiceProvider`) backed by the `config/templating.php` `fields` registry (model class → field name → accessor) via `ResolveTemplateField`/`BuildTemplateFieldContext` — extend that registry, not the resolver, when a new item type/field needs to be cross-referenceable.

Which `View.config` keys self-resolve when the view renders is also config-driven: `config/templating.php`'s `resolvable` entry (model class → config key → closure) gets that key's current raw value (not the model), the `ResolveTemplate` action, and the context, and returns the new value for that key — `ResolveTemplateStrings` extracts `$config[$key]`, calls the closure, and writes back whatever comes out, with no field-name-specific logic of its own. Add a new self-resolving config key there, not in `ResolveTemplateStrings`.

Any unresolved reference (unknown id, not-yet-answered, or a circular label/message reference) falls back to printing the raw `{{ ... }}` text — never throws. `content` escapes only the substituted *value* (`escapeValues: true`, since it renders via `{!! !!}`); `label`/`options[].label`/`message` must NOT be pre-escaped, since Blade's own `{{ }}` already escapes the whole resolved string at render time and double-escaping would corrupt entities.

`BuildTemplateFieldContext` sits early in `FetchConfigurationState::PREFIX` (right after `GuardAnchorOverride`) so `label()`/`message()` context is available to every later stage; `ResolveTemplateStrings` (mutates `componentContexts['scope']` Views in memory, never persists) only runs in the fetch pipeline, not commit — matches how commit's evaluation is discarded and re-fetched for rendering.
