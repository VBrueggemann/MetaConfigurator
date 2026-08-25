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
        $rootView = $configuration->configurator->rootView;

        $unansweredByViewId = $rootView === null
            ? collect()
            : $rootView->descendantsAndSelf()->pluck('id')->mapWithKeys(fn (string $viewId): array => [$viewId => null]);

        $persistedAnswers = $unansweredByViewId->merge($configuration->answers->pluck('value', 'view_id'));

        $evaluation->context['persistedAnswers'] = $persistedAnswers->all();

        $evaluation->context['answers'] = $persistedAnswers
            ->merge(collect($evaluation->pendingAnswers)
                ->mapWithKeys(fn (PendingAnswer $pendingAnswer): array => [$pendingAnswer->viewId => $pendingAnswer->value])
            )
            ->all();

        return $next($evaluation);
    }
}
