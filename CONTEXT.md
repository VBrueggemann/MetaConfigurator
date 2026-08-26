# MetaConfigurator

A rebuild of PartnerLink: an engine for building guided, multi-step **Configurators** — dynamic surveys/wizards whose flow, visibility, and validation react to what the respondent has answered so far.

## Language

**Configurator**:
The top-level definition of one guided flow — a tree of Views, plus the Rules that govern how a respondent moves through them.
_Avoid_: Survey, wizard, form (these are what a Configurator produces an *experience* of, not the definition itself)

**View**:
Any node in a Configurator's tree. Three types: `group` (a step/page, what used to be "Group"), `input` (a question — its `config['widget']` picks the control, e.g. text/select/checkbox/radio/date, open-ended and Blade-partial-driven rather than enumerated; there is no separate Option model, a select/multiselect widget's options live in config), and `display` (a static block — what used to be "Content" — used for things like intro or closing screens, or non-input content). A View's config stays slim — rendering-relevant detail only (a question's label, an input's widget/options, a display View's content). Validation is not config — it's expressed via Rules (see below). Views nest via a parent View, so the whole Configurator is one tree.
_Avoid_: Group, GroupItem, Option, ListItem, Content, node, component (these terms described the pre-unification model or are used for other things — see Rule's "component" below); also avoid `select`/`text_input`/`html` as View type names — those were an earlier, since-collapsed type list (see `.ai/rules/livewire.md`'s "View widgets are config-driven, not type-driven" rule)

**Configuration**:
One respondent's run through a Configurator — the accumulating record of what they've answered.
_Avoid_: Submission, response, session (Session is a distinct, more technical concept — see below)

**Answer**:
One respondent's answer to a single question-type View, within a Configuration.
_Avoid_: Response, value

**Respondent**:
The person filling out a Configuration. Anonymous — a Respondent has no account or identity beyond the Configuration they're building.
_Avoid_: User, participant

**Rule**:
One expression, evaluated against a Configuration's current Answers, that a named pipeline component (visibility, validation, or flow) acts on. Polarity is uniform: the expression evaluating true means the rule's effect applies — visible (visibility), valid (validation — the expression states the condition for validity, e.g. "required" is `answers['x'] != null`), or take this branch (flow). A Rule optionally targets a View (nullable — a flow Rule concerns the flow, not one View) and carries a `meta` payload the owning component defines the shape of (e.g. a flow Rule's meta names which View to go to when the expression holds). A Rule's expression may itself combine multiple conditions (via the expression language's own `&&`/`||`) — there is no separate Condition model or conjunction column. A fourth component, auto-select, is a recognized concept in the domain but out of scope until a Configurator actually needs it.
_Avoid_: Condition, conjunction, logic, branching (branching is what a flow Rule produces, not a synonym for Rule); also avoid "next-group" as the component name — it was renamed `Flow` (see `.ai/rules/rules-engine.md`)
