# Symfony ExpressionLanguage for Condition rules

Every Rule (VisibilityRule, ValidationRule, NextGroupRule) is driven by one or more Conditions, and a Condition needs some way to express "is this true given the Configuration's current Answers." The original PartnerLink stored each Condition as a `rule` string evaluated via `symfony/expression-language`, rather than as structured/tree-shaped rule data or hand-written PHP.

MetaConfigurator keeps `symfony/expression-language` for this rather than reinventing a condition grammar or moving to a structured (e.g. JSON-logic-style) representation. It's a proven fit for the original domain, and stored expression strings only need re-parsing, not migration, if this is ever revisited — the lock-in is the expression *syntax* authored into seeded/stored Conditions, not the storage shape.
