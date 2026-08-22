# Domain scope (v1)

Applies to: `app/Models/**`, `database/migrations/**`

- Do not add `Company`, `CompanyPreset`, `Preset`, `Service`, `ServiceSetting`, `Snapshot`, or `AutoSelectRule` models/migrations without first checking `docs/adr/0004-v1-scope-cuts.md` — these were deliberately cut from the original PartnerLink model, not overlooked.
- Configurators are authored via seeders/fixtures in v1, not an editor UI. Don't start an authoring UI without checking that ADR first — it documents why that's out of scope for now.
- An in-progress `Configuration` is identified via a value in the PHP session (not a URL param, not auth) — see `docs/adr/0005-configuration-persistence-and-identity.md` before changing how a Configuration is looked up.
