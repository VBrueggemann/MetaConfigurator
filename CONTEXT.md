# MetaConfigurator

A rebuild of PartnerLink: an engine for building guided, multi-step **Configurators** — dynamic surveys/wizards whose flow, visibility, and validation react to what the respondent has answered so far.

## Language

**Configurator**:
The top-level definition of one guided flow — an ordered set of Groups, plus the Rules that govern how a respondent moves through them.
_Avoid_: Survey, wizard, form (these are what a Configurator produces an *experience* of, not the definition itself)

**Group**:
One step/page of a Configurator, containing GroupItems and/or Content. Groups have a natural sequential order, which NextGroupRules may override.
_Avoid_: Step, page, screen

**GroupItem**:
One question within a Group. Has a type — single-select, multi-select, or text-input — that determines what kind of Answer it accepts.
_Avoid_: Question, field (Question is fine in prose, but GroupItem is the canonical term for the model)

**Option**:
One choice offered by a single-select or multi-select GroupItem.
_Avoid_: ListItem, choice

**Content**:
A static, non-question block attached to a Group or GroupItem — used for things like intro or closing screens. Carries no logic of its own.
_Avoid_: Text block, copy

**Configuration**:
One respondent's run through a Configurator — the accumulating record of what they've answered.
_Avoid_: Submission, response, session (Session is a distinct, more technical concept — see below)

**Answer**:
One respondent's answer to a single GroupItem, within a Configuration.
_Avoid_: Response, value

**AnsweredGroup**:
The grouping of a Configuration's Answers by the Group they belong to.
_Avoid_: -

**Respondent**:
The person filling out a Configuration. Anonymous — a Respondent has no account or identity beyond the Configuration they're building.
_Avoid_: User, participant

**Condition**:
A single expression, evaluated against a Configuration's current Answers, that resolves to true or false. Conditions combine via a conjunction (AND/OR) to form a Rule.
_Avoid_: Expression (Expression is the underlying mechanism; Condition is the domain term for one testable statement)

**Rule**:
A named behavior that fires when its Conditions hold. Three kinds are in scope: VisibilityRule (does a GroupItem appear), ValidationRule (is an Answer acceptable), NextGroupRule (which Group comes next). A fourth kind, AutoSelectRule, is a recognized concept in the domain but out of scope until a Configurator actually needs it.
_Avoid_: Logic, branching (branching is what NextGroupRule produces, not a synonym for Rule)
