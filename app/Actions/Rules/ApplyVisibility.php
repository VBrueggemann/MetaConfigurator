<?php

namespace App\Actions\Rules;

use App\Enums\RuleComponent;
use App\Models\Rule;
use App\Models\View;
use Closure;

final class ApplyVisibility
{
    public function __construct(
        private readonly EvaluateRule $evaluateRule,
    ) {}

    public function __invoke(GroupEvaluation $evaluation, Closure $next): mixed
    {
        $children = View::query()->where('parent_id', $evaluation->group->id)->orderBy('order')->get();

        $visible = [];

        foreach ($children as $child) {
            $rules = Rule::query()
                ->where('view_id', $child->id)
                ->where('component', RuleComponent::Visibility)
                ->orderBy('order')
                ->get();

            $isVisible = $rules->every(fn (Rule $rule) => ($this->evaluateRule)($rule, $evaluation->context));

            if ($isVisible) {
                $visible[] = $child->id;
            }
        }

        $evaluation->componentContexts[RuleComponent::Visibility->value] = $visible;

        return $next($evaluation);
    }
}
