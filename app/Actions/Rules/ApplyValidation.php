<?php

namespace App\Actions\Rules;

use App\Actions\DTO\ConfigurationEvaluation;
use App\Enums\ComponentType;
use Closure;

final class ApplyValidation
{
    public function __construct(
        private readonly EvaluateViewValidity $evaluateViewValidity,
    ) {}

    public function __invoke(ConfigurationEvaluation $evaluation, Closure $next): mixed
    {
        $errors = [];

        $visibility = $evaluation->componentContexts[ComponentType::Visibility->value] ?? [];
        $viewIds = $evaluation->componentContexts[ComponentType::Scope->value] ?? [];

        foreach ($viewIds as $viewId) {
            if (! ($visibility[$viewId] ?? false)) {
                continue;
            }

            $viewErrors = ($this->evaluateViewValidity)($viewId, $evaluation->context);

            if ($viewErrors !== []) {
                $errors[$viewId] = $viewErrors;
            }
        }

        $evaluation->componentContexts[ComponentType::Validation->value] = $errors;

        return $next($evaluation);
    }
}
