<?php

namespace App\Actions\Rules;

use App\Actions\DTO\ConfigurationEvaluation;
use App\Enums\ComponentType;
use App\Enums\RuleComponent;
use App\Models\Rule;
use Closure;

final class ApplyVisibility
{
    public function __construct(
        private readonly EvaluateRule $evaluateRule,
    ) {}

    public function __invoke(ConfigurationEvaluation $evaluation, Closure $next): mixed
    {
        $visibility = [];

        foreach ($evaluation->componentContexts[ComponentType::Scope->value] as $view) {
            $rules = $view->rules->filter(fn ($rule) => $rule->component === RuleComponent::Visibility);

            $ownRuleVisible = $rules->every(fn (Rule $rule) => ($this->evaluateRule)($rule, $evaluation->context));
            $parentVisible = $view->parent_id === null || ($visibility[$view->parent_id] ?? false);

            $visibility[$view->id] = $ownRuleVisible && $parentVisible;
        }

        $evaluation->componentContexts[ComponentType::Visibility->value] = $visibility;

        return $next($evaluation);
    }
}
