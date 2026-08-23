<?php

namespace App\Actions\Rules;

use App\Enums\RuleComponent;
use App\Models\Rule;

final class EvaluateViewValidity
{
    public function __construct(
        private readonly EvaluateRule $evaluateRule,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     * @return list<string>
     */
    public function __invoke(string $viewId, array $context): array
    {
        $errors = [];

        $rules = Rule::query()
            ->where('view_id', $viewId)
            ->where('component', RuleComponent::Validation)
            ->orderBy('order')
            ->get();

        foreach ($rules as $rule) {
            if (! ($this->evaluateRule)($rule, $context)) {
                $errors[] = $rule->meta['message'] ?? 'invalid';
            }
        }

        return $errors;
    }
}
