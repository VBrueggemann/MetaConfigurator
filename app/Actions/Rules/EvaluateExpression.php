<?php

namespace App\Actions\Rules;

use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

final class EvaluateExpression
{
    public function __construct(
        private readonly ExpressionLanguage $expressionLanguage,
    ) {}

    public function __invoke(string $expression, array $context = []): mixed
    {
        return $this->expressionLanguage->evaluate($expression, $context);
    }
}
