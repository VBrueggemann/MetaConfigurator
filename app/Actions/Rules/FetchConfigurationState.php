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

        return app(Pipeline::class)
            ->send($evaluation)
            ->through([BuildAnswerContext::class, ApplyCalculation::class, ApplyVisibility::class, ApplyValidation::class])
            ->via('__invoke')
            ->then(fn (ConfigurationEvaluation $evaluation): ConfigurationEvaluation => $evaluation);
    }
}
