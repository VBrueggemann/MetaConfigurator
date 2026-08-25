<?php

namespace App\Actions\Rules;

use App\Actions\DTO\ConfigurationEvaluation;
use Closure;

final class GuardAnchorOverride
{
    public function __invoke(ConfigurationEvaluation $evaluation, Closure $next): mixed
    {
        if ($evaluation->anchorViewId === null) {
            return $next($evaluation);
        }

        $isExistingRelevantChunkAnchor = $evaluation->configuration->relevantAnswerChunks
            ->contains(fn ($chunk) => $chunk->anchor_view_id === $evaluation->anchorViewId);

        if (! $isExistingRelevantChunkAnchor) {
            $evaluation->anchorViewId = null;
        }

        return $next($evaluation);
    }
}
