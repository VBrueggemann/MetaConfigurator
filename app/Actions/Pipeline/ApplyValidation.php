<?php

namespace App\Actions\Pipeline;

use App\Actions\DTO\ConfigurationEvaluation;
use App\Enums\ComponentType;
use App\Enums\RuleComponent;
use App\Exceptions\ValidationFailedException;
use App\Models\Rule;
use App\Models\View;
use Closure;
use Illuminate\Support\Collection;

final class ApplyValidation
{
    public function __construct(
        private readonly EvaluateRule $evaluateRule,
        private readonly bool $throwOnFailure,
    ) {}

    public function __invoke(ConfigurationEvaluation $evaluation, Closure $next): mixed
    {
        $roots = collect($evaluation->componentContexts[ComponentType::Scope->value] ?? []);

        $pendingViewIds = collect($evaluation->pendingAnswers)->pluck('viewId')->all();
        $pendingPaths = $roots->toList()->whereIn('id', $pendingViewIds)->pluck('path')->implode('|');

        foreach ($roots as $view) {
            $this->validate($view, $evaluation->context, $pendingPaths);
        }

        if ($this->throwOnFailure && $this->hasFailingValidationRule($roots)) {
            throw new ValidationFailedException($roots->toList()->pluck('id')->all());
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

    /**
     * @param  Collection<int, View>  $roots
     */
    private function hasFailingValidationRule(Collection $roots): bool
    {
        return $roots->toList()->contains(
            fn (View $view): bool => $view->rules->contains(fn (Rule $rule): bool => $rule->component === RuleComponent::Validation)
        );
    }
}
