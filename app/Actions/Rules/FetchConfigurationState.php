<?php

namespace App\Actions\Rules;

use App\Actions\DTO\ConfigurationEvaluation;
use App\Models\Configuration;
use Illuminate\Pipeline\Pipeline;

final class FetchConfigurationState
{
    public function __construct(
        private readonly BuildAnswerContext $buildAnswerContext,
    ) {}

    /**
     * @param  array<string, mixed>  $pendingAnswers
     */
    public function __invoke(Configuration $configuration, array $pendingAnswers = []): ConfigurationEvaluation
    {
        $evaluation = new ConfigurationEvaluation(
            $configuration,
            $pendingAnswers,
            ($this->buildAnswerContext)($configuration, $pendingAnswers),
        );

        return app(Pipeline::class)
            ->send($evaluation)
            ->through([ApplyCalculation::class, ApplyVisibility::class, ApplyScope::class, ApplyValidation::class])
            ->via('__invoke')
            ->then(fn (ConfigurationEvaluation $evaluation): ConfigurationEvaluation => $evaluation);
    }
}
