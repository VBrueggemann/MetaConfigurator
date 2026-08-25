<?php

namespace App\Actions\Rules;

use App\Actions\DTO\ConfigurationEvaluation;
use App\Actions\DTO\PendingAnswer;
use App\Models\Configuration;
use Illuminate\Pipeline\Pipeline;

final class CommitConfigurationAnswers
{
    public function __construct(
        private readonly FetchConfigurationState $fetchConfigurationState,
    ) {}

    /**
     * @param  array<int, PendingAnswer>  $pendingAnswers
     */
    public function __invoke(Configuration $configuration, array $pendingAnswers, ?string $anchorViewId = null): ConfigurationEvaluation
    {
        $evaluation = ($this->fetchConfigurationState)($configuration, $pendingAnswers, $anchorViewId);

        return app(Pipeline::class)
            ->send($evaluation)
            ->through([SupersedeAnswerChunks::class, CommitAnswers::class])
            ->thenReturn();
    }
}
