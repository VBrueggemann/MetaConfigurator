<?php

namespace App\Actions\Rules;

use App\Actions\DTO\ConfigurationEvaluation;
use App\Enums\ComponentType;
use App\Models\Answer;
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
            foreach ($evaluation->pendingAnswers as $viewId => $value) {
                Answer::updateOrCreate(
                    ['configuration_id' => $evaluation->configuration->id, 'view_id' => $viewId],
                    ['value' => $value],
                );
            }
        });

        return $next($evaluation);
    }
}
