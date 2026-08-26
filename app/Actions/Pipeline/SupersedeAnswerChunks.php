<?php

namespace App\Actions\Pipeline;

use App\Actions\DTO\ConfigurationEvaluation;
use Closure;

final class SupersedeAnswerChunks
{
    public function __invoke(ConfigurationEvaluation $evaluation, Closure $next): mixed
    {
        $existingChunk = $evaluation->configuration->relevantAnswerChunks
            ->first(fn ($chunk) => $chunk->anchor_view_id === $evaluation->anchorViewId);

        if ($existingChunk !== null) {
            $evaluation->configuration->relevantAnswerChunks()
                ->where('sequence', '>=', $existingChunk->sequence)
                ->update(['relevant' => false]);
        }

        return $next($evaluation);
    }
}
