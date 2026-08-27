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

final class GuardValidationFailure
{
    public function __invoke(ConfigurationEvaluation $evaluation, Closure $next): mixed
    {
        $roots = collect($evaluation->componentContexts[ComponentType::Scope->value] ?? []);

        if ($this->hasFailingValidationRule($roots)) {
            throw new ValidationFailedException($roots->toList()->pluck('id')->all());
        }

        return $next($evaluation);
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
