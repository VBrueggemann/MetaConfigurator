<?php

namespace App\Actions\Rules;

use App\Actions\DTO\ConfigurationEvaluation;
use App\Enums\ComponentType;
use Closure;

final class SupersedeAnswerChunks
{
    public function __invoke(ConfigurationEvaluation $evaluation, Closure $next): mixed
    {
        if (($evaluation->componentContexts[ComponentType::Validation->value] ?? []) !== []) {
            return $next($evaluation);
        }

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
