<?php

namespace App\Actions\Rules;

use App\Actions\DTO\ConfigurationEvaluation;
use App\Models\Configuration;
use Illuminate\Pipeline\Pipeline;

final class CommitConfigurationAnswers
{
    public function __construct(
        private readonly FetchConfigurationState $fetchConfigurationState,
    ) {}

    /**
     * @param  array<string, mixed>  $pendingAnswers
     */
    public function __invoke(Configuration $configuration, array $pendingAnswers): ConfigurationEvaluation
    {
        $evaluation = ($this->fetchConfigurationState)($configuration, $pendingAnswers);

        return app(Pipeline::class)
            ->send($evaluation)
            ->through([CommitAnswers::class])
            ->via('__invoke')
            ->then(fn (ConfigurationEvaluation $evaluation): ConfigurationEvaluation => $evaluation);
    }
}
