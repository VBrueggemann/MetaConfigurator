<?php

namespace App\Actions\Pipeline;

use App\Actions\DTO\ConfigurationEvaluation;
use Closure;

final class ResolveTemplateStrings
{
    public function __construct(
        private readonly ResolveTemplate $resolveTemplate,
    ) {}

    public function __invoke(ConfigurationEvaluation $evaluation, Closure $next): mixed
    {
        foreach (config('templating.resolvable', []) as $resolvableConfig) {
            $items = $evaluation->componentContexts[$resolvableConfig['context']] ?? collect();

            ($resolvableConfig['iterate'])(
                $items,
                fn ($item) => ($resolvableConfig['resolve'])($item, $resolvableConfig['keys'], $this->resolveTemplate, $evaluation->context),
            );
        }

        return $next($evaluation);
    }
}
