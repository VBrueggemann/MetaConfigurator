# v1 scope: seeded Configurators, no multi-tenancy, no AutoSelect

The original PartnerLink model included an editor UI for authoring Configurators, plus Company/CompanyPreset (multi-tenancy), Preset (reusable templates), Service/ServiceSetting, Snapshot (versioning), and AutoSelectRule.

v1 of MetaConfigurator deliberately excludes all of these:

- **No editor UI.** Configurators are authored via seeders/fixtures. The engine (View tree/Rule/Pipeline) is proven against one fictional survey before any effort goes into a tool for building Configurators interactively.
- **No Company/CompanyPreset/Preset/Service/ServiceSetting.** There is one respondent-facing surface, no multi-tenant ownership, and no template/preset reuse. These are the natural seam for multi-tenancy later, but nothing in v1 needs them.
- **No Snapshot.** Configuration state is just its live Answers; no versioned freeze of a Configurator's definition at fill-time.
- **No auto-select component.** The domain still recognizes it as a Rule component (see CONTEXT.md), but no v1 Configurator exercises it, so it isn't implemented.

Each of these is a deliberate scope cut, not an oversight — a future reader adding multi-tenancy or an editor should treat this as the point where that work begins, not as something that was forgotten.
