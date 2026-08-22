<?php

namespace App\Actions\Rules;

use App\Models\Rule;
use Throwable;

final class EvaluateRule
{
    public function __construct(
        private readonly EvaluateExpression $evaluateExpression,
    ) {}

    /**
     * Evaluates a Rule's expression, defaulting to `false` if it references
     * a field the context doesn't have (e.g. an unanswered View) rather than
     * throwing — an unanswered field simply doesn't satisfy the rule.
     */
    public function __invoke(Rule $rule, array $context): bool
    {
        try {
            return (bool) ($this->evaluateExpression)($rule->expression, $context);
        } catch (Throwable) {
            return false;
        }
    }
}
