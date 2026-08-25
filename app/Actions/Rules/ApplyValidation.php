<?php

namespace App\Actions\Rules;

use App\Actions\DTO\ConfigurationEvaluation;
use App\Enums\ComponentType;
use App\Enums\RuleComponent;
use App\Models\View;
use Closure;

final class ApplyValidation
{
    public function __construct(
        private readonly EvaluateRule $evaluateRule
    ) {}

    protected array $errors = [];

    public function __invoke(ConfigurationEvaluation $evaluation, Closure $next): mixed
    {
        $rootView = $evaluation->componentContexts[ComponentType::Scope->value]->first();
        $pendingViews = $evaluation->componentContexts[ComponentType::PendingViews->value]->pluck('id')->all();
        $visibility = $evaluation->componentContexts[ComponentType::Visibility->value] ?? [];

        if ($rootView) {
            $this->validate($rootView, $evaluation->context, $pendingViews, $visibility);
        }

        $evaluation->componentContexts[ComponentType::Validation->value] = $this->errors;

        return $next($evaluation);
    }

    /**
     * @param  array<string, bool>  $visibility
     */
    private function validate(View $view, array $context, array $pendingViews, array $visibility)
    {
        if (($visibility[$view->id] ?? true) && in_array($view->id, $pendingViews)) {
            $rules = $view->rules->filter(fn ($rule) => $rule->component === RuleComponent::Validation);

            foreach ($rules as $rule) {
                if (! ($this->evaluateRule)($rule, $context)) {
                    $this->errors[$view->id][] = $rule->meta['message'] ?? 'invalid';
                }
            }
        }

        $view->children->each(fn (View $child) => $this->validate($child, $context, $pendingViews, $visibility));
    }
}
