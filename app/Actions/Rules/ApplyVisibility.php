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
        $views = View::query()
            ->where('configurator_id', $evaluation->configuration->configurator_id)
            ->tree()
            ->depthFirst()
            ->get();

        $rulesByViewId = Rule::query()
            ->whereIn('view_id', $views->pluck('id'))
            ->where('component', RuleComponent::Visibility)
            ->orderBy('order')
            ->get()
            ->groupBy('view_id');

        $visibility = [];

        foreach ($views as $view) {
            $rules = $rulesByViewId->get($view->id, collect());

            $ownRuleVisible = $rules->every(fn (Rule $rule) => ($this->evaluateRule)($rule, $evaluation->context));
            $parentVisible = $view->parent_id === null || ($visibility[$view->parent_id] ?? false);

            $visibility[$view->id] = $ownRuleVisible && $parentVisible;
        }

        $evaluation->componentContexts[ComponentType::Visibility->value] = $visibility;

        return $next($evaluation);
    }
}
