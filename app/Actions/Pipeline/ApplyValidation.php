<?php

namespace App\Actions\Pipeline;

use App\Actions\DTO\ConfigurationEvaluation;
use App\Enums\RuleComponent;
use App\Models\Rule;
use App\Models\View;
use Closure;

final class ApplyValidation
{
    public function __construct(
        private readonly EvaluateRule $evaluateRule,
    ) {}

    public function __invoke(ConfigurationEvaluation $evaluation, Closure $next): mixed
    {
        $roots = collect($evaluation->componentContexts['scope'] ?? []);

        $pendingViewIds = collect($evaluation->pendingAnswers)->pluck('viewId')->all();
        $pendingPaths = $roots->toList()->whereIn('id', $pendingViewIds)->pluck('path')->implode('|');

        foreach ($roots as $view) {
            $this->validate($view, $evaluation->context, $pendingPaths);
        }

        return $next($evaluation);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function validate(View $view, array $context, string $pendingPaths): void
    {
        $isPending = $pendingPaths !== '' && str_contains($pendingPaths, $view->id);

        $view->setRelation('rules', $view->rules->reject(
            fn (Rule $rule): bool => $rule->component === RuleComponent::Validation
                && ($isPending ? ($this->evaluateRule)($rule, $context) : true)
        ));

        $view->children->each(fn (View $child) => $this->validate($child, $context, $pendingPaths));
    }
}
