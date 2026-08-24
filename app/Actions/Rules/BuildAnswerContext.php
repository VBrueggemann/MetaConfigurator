<?php

namespace App\Actions\Rules;

use App\Actions\DTO\ConfigurationEvaluation;
use App\Actions\DTO\PendingAnswer;
use Closure;

final class BuildAnswerContext
{
    public function __invoke(ConfigurationEvaluation $evaluation, Closure $next): mixed
    {
        $configuration = $evaluation->configuration;

        $evaluation->context['answers'] = $configuration->answers->pluck('value', 'view_id')
            ->merge(collect($evaluation->pendingAnswers)
                ->mapWithKeys(fn (PendingAnswer $pendingAnswer): array => [$pendingAnswer->viewId => $pendingAnswer->value])
            )
            ->all();

        return $next($evaluation);
    }
}
