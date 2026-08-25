<?php

namespace App\Actions\Rules;

use App\Actions\DTO\ConfigurationEvaluation;
use App\Enums\ComponentType;
use Closure;
use Illuminate\Support\Facades\DB;

final class CommitAnswers
{
    public function __invoke(ConfigurationEvaluation $evaluation, Closure $next): mixed
    {
        if (($evaluation->componentContexts[ComponentType::Validation->value] ?? []) !== []) {
            return $next($evaluation);
        }

        DB::transaction(function () use ($evaluation) {
            $sequence = $evaluation->configuration->answerChunks()->max('sequence') + 1;

            $chunk = $evaluation->configuration->relevantAnswerChunks()->create([
                'anchor_view_id' => $evaluation->anchorViewId,
                'sequence' => $sequence,
            ]);

            foreach ($evaluation->pendingAnswers as $pendingAnswer) {
                $chunk->answers()->create([
                    'configuration_id' => $evaluation->configuration->id,
                    'view_id' => $pendingAnswer->viewId,
                    'value' => $pendingAnswer->value,
                ]);
            }
        });

        return $next($evaluation);
    }
}
