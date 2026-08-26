<?php

namespace App\Actions\Pipeline;

use App\Actions\DTO\ConfigurationEvaluation;
use App\Enums\ComponentType;
use App\Models\View;
use Closure;

final class ApplyBreadcrumbs
{
    public function __invoke(ConfigurationEvaluation $evaluation, Closure $next): mixed
    {
        $chunks = $evaluation->configuration->relevantAnswerChunks;

        $anchorViewsById = View::query()->whereIn('id', $chunks->pluck('anchor_view_id'))->get()->keyBy('id');

        $evaluation->componentContexts[ComponentType::Breadcrumbs->value] = $chunks
            ->map(fn ($chunk) => $anchorViewsById->get($chunk->anchor_view_id))
            ->filter()
            ->values();

        return $next($evaluation);
    }
}
