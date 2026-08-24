<?php

namespace App\Actions\Rules;

use App\Actions\DTO\ConfigurationEvaluation;
use App\Actions\DTO\PendingAnswer;
use App\Enums\ComponentType;
use Closure;

final class BuildAnswerContext
{
    public function __invoke(ConfigurationEvaluation $evaluation, Closure $next): mixed
    {
        $configuration = $evaluation->configuration;
        $configuration->load('answers');
        $configuration->loadMissing('configurator.rootView');

        $persistedAnswersByViewId = $configuration->answers->pluck('value', 'view_id');
        $pendingAnswersByViewId = collect($evaluation->pendingAnswers)
            ->mapWithKeys(fn (PendingAnswer $pendingAnswer): array => [$pendingAnswer->viewId => $pendingAnswer->value]);

        $views = $configuration->configurator->rootView === null
            ? collect()
            : $configuration->configurator->rootView->descendantsAndSelf()->with(['rules'])->depthFirst()->get();

        $unansweredByViewId = $views->pluck('id')->mapWithKeys(fn (string $viewId): array => [$viewId => null]);

        $evaluation->context['answers'] = $unansweredByViewId->merge($persistedAnswersByViewId)->merge($pendingAnswersByViewId)->all();

        $evaluation->context['views'] = $views->pluck('id');

        $evaluation->componentContexts[ComponentType::Views->value] = $views;

        return $next($evaluation);
    }
}
