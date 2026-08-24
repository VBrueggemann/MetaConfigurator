<?php

namespace App\Actions\Rules;

use App\Actions\DTO\ConfigurationEvaluation;
use App\Enums\ComponentType;
use App\Enums\RuleComponent;
use App\Models\Rule;
use App\Models\View;
use Closure;

final class ApplyVisibility
{
    public function __construct(
        private readonly EvaluateRule $evaluateRule,
    ) {}

    public function __invoke(ConfigurationEvaluation $evaluation, Closure $next): mixed
    {
        $visibility = [];

        $this->checkChildVisibility($evaluation->componentContexts[ComponentType::Scope->value]->first(), $evaluation->context, $visibility);

        $evaluation->pendingAnswers = collect($evaluation->pendingAnswers)->filter(fn ($answer) => $visibility[$answer->viewId] ?? false)->values()->toArray();

        return $next($evaluation);
    }

    private function checkChildVisibility(View $view, array $context, array &$visibility): bool
    {
        $rules = $view->rules->filter(fn ($rule) => $rule->component === RuleComponent::Visibility);

        $visible = $rules->every(fn (Rule $rule) => ($this->evaluateRule)($rule, $context));
        $visibility[$view->id] = $visible;

        if (! $visible) {
            return false;
        }

        $childVisibility = [];
        $view->children->each(function (View $child) use ($context, &$visibility, &$childVisibility) {
            $childVisibility[$child->id] = $this->checkChildVisibility($child, $context, $visibility);
        });

        $view->setRelation('children', $view->children->filter(fn (View $child) => $childVisibility[$child->id])->values());

        return true;
    }
}
