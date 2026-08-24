<?php

namespace App\Actions\Rules;

use App\Actions\DTO\ConfigurationEvaluation;
use App\Actions\DTO\PendingAnswer;
use App\Models\Configuration;
use Illuminate\Pipeline\Pipeline;

final class FetchConfigurationState
{
    /**
     * @param  array<int, PendingAnswer>  $pendingAnswers
     */
    public function __invoke(Configuration $configuration, array $pendingAnswers = []): ConfigurationEvaluation
    {
        $evaluation = new ConfigurationEvaluation($configuration, $pendingAnswers);

        $configuration->load('answers');
        $configuration->loadMissing('configurator.rootView');

        if (! $configuration->configurator->rootView) {
            abort(404);
        }

        return app(Pipeline::class)
            ->send($evaluation)
            ->through([BuildAnswerContext::class, ApplyCalculation::class, ApplyFlow::class, ApplyVisibility::class, ApplyValidation::class, ApplyBreadcrumbs::class])
            ->thenReturn();
    }
}
