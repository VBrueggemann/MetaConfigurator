<?php

namespace App\Actions\Rules;

use App\Enums\RuleComponent;
use App\Models\Rule;
use Closure;

final class ApplyValidation
{
    public function __construct(
        private readonly EvaluateRule $evaluateRule,
    ) {}

    public function __invoke(GroupEvaluation $evaluation, Closure $next): mixed
    {
        $errors = [];

        foreach ($evaluation->componentContexts[RuleComponent::Visibility->value] ?? [] as $viewId) {
            $rules = Rule::query()
                ->where('view_id', $viewId)
                ->where('component', RuleComponent::Validation)
                ->orderBy('order')
                ->get();

            foreach ($rules as $rule) {
                if (! ($this->evaluateRule)($rule, $evaluation->context)) {
                    $errors[$viewId][] = $rule->meta['message'] ?? 'invalid';
                }
            }
        }

        $evaluation->componentContexts[RuleComponent::Validation->value] = $errors;

        return $next($evaluation);
    }
}
