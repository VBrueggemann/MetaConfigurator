<?php

namespace App\Actions\Pipeline;

use App\Actions\DTO\ConfigurationEvaluation;
use App\Enums\ComponentType;
use App\Models\View;
use Closure;

final class ResolveTemplateStrings
{
    public function __construct(
        private readonly ResolveTemplate $resolveTemplate,
    ) {}

    public function __invoke(ConfigurationEvaluation $evaluation, Closure $next): mixed
    {
        $scope = $evaluation->componentContexts[ComponentType::Scope->value] ?? collect();

        $scope->each(fn (View $view) => $this->resolve($view, $evaluation->context));

        return $next($evaluation);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function resolve(View $view, array $context): void
    {
        $config = $view->config;

        foreach (config('templating.resolvable')[View::class] ?? [] as $key => $resolve) {
            if (isset($config[$key])) {
                $config[$key] = $resolve($config[$key], $this->resolveTemplate, $context);
            }
        }

        $view->config = $config;

        $view->children->each(fn (View $child) => $this->resolve($child, $context));
    }
}
