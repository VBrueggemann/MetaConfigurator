# Symfony ExpressionLanguage for Rule expressions

Every Rule needs some way to express "is this true given the Configuration's current Answers." The original PartnerLink stored this as a `rule` string on a separate Condition model, evaluated via `symfony/expression-language`, rather than as structured/tree-shaped rule data or hand-written PHP.

MetaConfigurator keeps `symfony/expression-language` for this rather than reinventing a condition grammar or moving to a structured (e.g. JSON-logic-style) representation. It's a proven fit for the original domain, and stored expression strings only need re-parsing, not migration, if this is ever revisited — the lock-in is the expression *syntax* authored into a Rule's expression, not the storage shape. Because the expression language already supports `&&`/`||` natively, a Rule's expression can itself encode compound logic — this is also what makes the separate Condition model (and its conjunction column) droppable; see ADR 0006.
