<?php

namespace App\Actions\Pipeline;

use App\Actions\DTO\ConfigurationEvaluation;
use App\Actions\DTO\PendingAnswer;
use App\Models\Answer;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CommitAnswers
{
    public function __invoke(ConfigurationEvaluation $evaluation, Closure $next): mixed
    {
        DB::transaction(function () use ($evaluation) {
            $sequence = $evaluation->configuration->answerChunks()->max('sequence') + 1;

            $chunk = $evaluation->configuration->relevantAnswerChunks()->create([
                'anchor_view_id' => $evaluation->anchorViewId,
                'sequence' => $sequence,
            ]);

            if ($evaluation->pendingAnswers !== []) {
                $now = now();

                Answer::query()->insert(array_map(fn (PendingAnswer $pendingAnswer) => [
                    'id' => Str::uuid()->toString(),
                    'configuration_id' => $evaluation->configuration->id,
                    'view_id' => $pendingAnswer->viewId,
                    'answer_chunk_id' => $chunk->id,
                    'value' => json_encode($pendingAnswer->value),
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $evaluation->pendingAnswers));
            }
        });

        return $next($evaluation);
    }
}
