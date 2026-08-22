# Domain scope (v1)

Applies to: `app/Models/**`, `database/migrations/**`

- Do not add `Company`, `CompanyPreset`, `Preset`, `Service`, `ServiceSetting`, `Snapshot`, or an auto-select Rule component without first checking `docs/adr/0004-v1-scope-cuts.md` — these were deliberately cut from the original PartnerLink model, not overlooked.
- Configurators are authored via seeders/fixtures in v1, not an editor UI. Don't start an authoring UI without checking that ADR first — it documents why that's out of scope for now.
- An in-progress `Configuration` is identified via a value in the PHP session (not a URL param, not auth) — see `docs/adr/0005-configuration-persistence-and-identity.md` before changing how a Configuration is looked up.
- `Group`, `GroupItem`, `Option`, and `Condition` are **not** separate models — they were unified into `View` (self-referential tree) and `Rule` respectively. Don't recreate them; see `docs/adr/0006-view-unification-and-rule-condition-merge.md`.
