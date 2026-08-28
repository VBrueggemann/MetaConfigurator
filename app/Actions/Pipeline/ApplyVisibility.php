<?php

namespace App\Actions\Pipeline;

use App\Actions\DTO\ConfigurationEvaluation;
use App\Enums\RuleComponent;
use App\Models\Rule;
use App\Models\View;
use Closure;
use Illuminate\Support\Collection;

final class ApplyVisibility
{
    public function __construct(
        private readonly EvaluateRule $evaluateRule,
    ) {}

    public function __invoke(ConfigurationEvaluation $evaluation, Closure $next): mixed
    {
        $visibility = [];

        $scope = $evaluation->componentContexts['scope'] ?? collect();

        foreach ($scope->toList() as $view) {
            $rules = $view->rules->filter(fn ($rule) => $rule->component === RuleComponent::Visibility);

            $ownRuleVisible = $view->id === $evaluation->anchorViewId
                || $rules->every(fn (Rule $rule) => ($this->evaluateRule)($rule, $evaluation->context));
            $parentVisible = $view->parent_id === null || ($visibility[$view->parent_id] ?? false);

            $visibility[$view->id] = $ownRuleVisible && $parentVisible;
        }

        $evaluation->pendingAnswers = collect($evaluation->pendingAnswers)->filter(fn ($answer) => $visibility[$answer->viewId] ?? false)->values()->toArray();

        $this->pruneInvisible($scope, $visibility);

        return $next($evaluation);
    }

    /**
     * @param  Collection<int, View>  $views
     * @param  array<string, bool>  $visibility
     */
    private function pruneInvisible(Collection $views, array $visibility): void
    {
        foreach ($views as $view) {
            $view->setRelation('children', $view->children->filter(fn (View $child) => $visibility[$child->id] ?? false)->values());

            $this->pruneInvisible($view->children, $visibility);
        }
    }
}
