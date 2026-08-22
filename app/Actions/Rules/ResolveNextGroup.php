<?php

namespace App\Actions\Rules;

use App\Enums\RuleComponent;
use App\Models\Rule;
use App\Models\View;
use Closure;

final class ResolveNextGroup
{
    public function __construct(
        private readonly EvaluateRule $evaluateRule,
    ) {}

    public function __invoke(GroupEvaluation $evaluation, Closure $next): mixed
    {
        if (! empty($evaluation->componentContexts[RuleComponent::Validation->value] ?? [])) {
            $evaluation->componentContexts[RuleComponent::NextGroup->value] = null;

            return $next($evaluation);
        }

        $rules = Rule::query()
            ->where('view_id', $evaluation->group->id)
            ->where('component', RuleComponent::NextGroup)
            ->orderBy('order')
            ->get();

        foreach ($rules as $rule) {
            if (($this->evaluateRule)($rule, $evaluation->context)) {
                $evaluation->componentContexts[RuleComponent::NextGroup->value] = $rule->meta['next_view_id'] ?? null;

                return $next($evaluation);
            }
        }

        $evaluation->componentContexts[RuleComponent::NextGroup->value] = $this->nextSiblingId($evaluation->group);

        return $next($evaluation);
    }

    private function nextSiblingId(View $group): ?string
    {
        $siblings = View::query()
            ->where('configurator_id', $group->configurator_id)
            ->whereNull('parent_id')
            ->orderBy('order')
            ->get();

        $index = $siblings->search(fn (View $sibling) => $sibling->id === $group->id);

        return $siblings->get($index + 1)?->id;
    }
}
