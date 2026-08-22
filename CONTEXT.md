# MetaConfigurator

A rebuild of PartnerLink: an engine for building guided, multi-step **Configurators** — dynamic surveys/wizards whose flow, visibility, and validation react to what the respondent has answered so far.

## Language

**Configurator**:
The top-level definition of one guided flow — a tree of Views, plus the Rules that govern how a respondent moves through them.
_Avoid_: Survey, wizard, form (these are what a Configurator produces an *experience* of, not the definition itself)

**View**:
Any node in a Configurator's tree — a step/page (what used to be a "Group"), a question within a step (what used to be a "GroupItem"), or a choice offered by a question (what used to be an "Option"). A View's `type` says which of these it is; type-specific detail (a question's answer-type — single-select, multi-select, or text — and any validation limits) lives in that View's config, not in separate models. Views nest via a parent View, so the whole Configurator is one tree.
_Avoid_: Group, GroupItem, Option, ListItem, node, component (these terms described the pre-unification model or are used for other things — see Rule's "component" below)

**Content**:
A static, non-logic block attached to a View — used for things like intro or closing screens.
_Avoid_: Text block, copy

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
One expression, evaluated against a Configuration's current Answers, that a named pipeline component (visibility, validation, or next-group) acts on. A Rule optionally targets a View (nullable — a next-group Rule concerns the flow, not one View) and carries a `meta` payload the owning component defines the shape of (e.g. a next-group Rule's meta names which View to go to when the expression holds). A Rule's expression may itself combine multiple conditions (via the expression language's own `&&`/`||`) — there is no separate Condition model or conjunction column. A fourth component, auto-select, is a recognized concept in the domain but out of scope until a Configurator actually needs it.
_Avoid_: Condition, conjunction, logic, branching (branching is what a next-group Rule produces, not a synonym for Rule)
