<?php

namespace App\Actions\Rules;

use App\Models\Rule;
use Throwable;

final class EvaluateRule
{
    public function __construct(
        private readonly EvaluateExpression $evaluateExpression,
    ) {}

    public function __invoke(Rule $rule, array $context): bool
    {
        try {
            return (bool) ($this->evaluateExpression)($rule->expression, $context);
        } catch (Throwable) {
            return false;
        }
    }
}
