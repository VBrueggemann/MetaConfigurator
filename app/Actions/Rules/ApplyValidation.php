<?php

namespace App\Actions\Rules;

use App\Actions\DTO\ConfigurationEvaluation;
use App\Enums\ComponentType;
use App\Enums\RuleComponent;
use App\Models\View;
use Closure;
use Illuminate\Support\Collection;

final class ApplyValidation
{
    public function __construct(
        private readonly EvaluateRule $evaluateRule
    ) {}

    public function __invoke(ConfigurationEvaluation $evaluation, Closure $next): mixed
    {
        $errors = [];

        $views = $evaluation->componentContexts[ComponentType::Views->value] ?? new Collection;

        $pendingPaths = $views
            ->whereIn('id', collect($evaluation->pendingAnswers)->pluck('viewId'))
            ->pluck('path');

        $viewsToValidate = $views->filter(
            fn (View $view) => $pendingPaths->contains(fn (string $pendingPath) => str($pendingPath)->startsWith($view->path))
        );

        foreach ($viewsToValidate as $view) {
            $rules = $view->rules->filter(fn ($rule) => $rule->component === RuleComponent::Validation);
            foreach ($rules as $rule) {
                if (! ($this->evaluateRule)($rule, $evaluation->context)) {
                    $errors[$view->id][] = $rule->meta['message'] ?? 'invalid';
                }
            }
        }

        $evaluation->componentContexts[ComponentType::Validation->value] = $errors;

        return $next($evaluation);
    }
}
