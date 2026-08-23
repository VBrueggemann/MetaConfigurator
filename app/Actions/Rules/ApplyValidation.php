<?php

namespace App\Actions\Rules;

use App\Actions\DTO\ConfigurationEvaluation;
use App\Enums\ComponentType;
use App\Models\View;
use Closure;
use Illuminate\Support\Collection;

final class ApplyValidation
{
    public function __construct(
        private readonly EvaluateViewValidity $evaluateViewValidity,
    ) {}

    public function __invoke(ConfigurationEvaluation $evaluation, Closure $next): mixed
    {
        $errors = [];

        $visibility = $evaluation->componentContexts[ComponentType::Visibility->value] ?? [];
        $scopedViews = $evaluation->componentContexts[ComponentType::Scope->value] ?? new Collection;

        foreach ($scopedViews as $view) {
            /** @var View $view */
            if (! ($visibility[$view->id] ?? false)) {
                continue;
            }

            $viewErrors = ($this->evaluateViewValidity)($view->id, $evaluation->context);

            if ($viewErrors !== []) {
                $errors[$view->id] = $viewErrors;
            }
        }

        $evaluation->componentContexts[ComponentType::Validation->value] = $errors;

        return $next($evaluation);
    }
}
