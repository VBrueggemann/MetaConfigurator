# Single Laravel + Livewire monolith, not a split API + SPA architecture

The original PartnerLink was a Laravel API backend plus two separate Vue 3 SPAs (an `editor` and a `configurator`), coordinated through generated TypeScript types and Ziggy routes. That project's complexity — partly attributable to this split, and to coordinating multiple frontend stakeholders around it — is a large part of why it "went off the rails."

MetaConfigurator is rebuilt from scratch as one Laravel + Livewire codebase, with the editor/runtime distinction kept only as an internal conceptual split (separate Livewire components/routes), not as separate deployable apps. This trades away the original's independent frontend-deploy flexibility and its typed API boundary, in exchange for a much smaller surface area appropriate for a solo, AI-assisted rebuild.
