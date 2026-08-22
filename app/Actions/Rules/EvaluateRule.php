<?php

namespace App\Actions\Rules;

use App\Models\Rule;

final class EvaluateRule
{
    public function __construct(
        private readonly EvaluateExpression $evaluateExpression,
    ) {}

    public function __invoke(Rule $rule, array $context): bool
    {
        return (bool) ($this->evaluateExpression)($rule->expression, $context);
    }
}
