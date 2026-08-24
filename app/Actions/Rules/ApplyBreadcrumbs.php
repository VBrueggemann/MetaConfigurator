<?php

namespace App\Actions\Rules;

use App\Actions\DTO\ConfigurationEvaluation;
use App\Enums\ComponentType;
use App\Models\Component;
use App\Models\View;
use Closure;
use Illuminate\Support\Collection;

final class ApplyBreadcrumbs
{
    public function __invoke(ConfigurationEvaluation $evaluation, Closure $next): mixed
    {
        $views = $evaluation->componentContexts[ComponentType::AnsweredViews->value] ?? new Collection;

        $component = Component::query()
            ->where('configurator_id', $evaluation->configuration->configurator_id)
            ->where('type', ComponentType::Scope)
            ->first();

        $depth = $component?->config['depth'] ?? 0;

        $evaluation->componentContexts[ComponentType::Breadcrumbs->value] = $views
            ->filter(fn (View $view) => (int) $view->depth < $depth)
            ->values();

        return $next($evaluation);
    }
}
