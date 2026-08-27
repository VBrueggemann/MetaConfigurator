<?php

namespace App\Actions;

use App\Actions\DTO\ConfigurationEvaluation;
use App\Actions\DTO\PendingAnswer;
use App\Actions\Pipeline\ApplyCalculation;
use App\Actions\Pipeline\ApplyFlow;
use App\Actions\Pipeline\ApplyValidation;
use App\Actions\Pipeline\ApplyVisibility;
use App\Actions\Pipeline\CollectAndBatchFetchExternalReferences;
use App\Actions\Pipeline\CommitAnswers;
use App\Actions\Pipeline\GuardAnchorOverride;
use App\Actions\Pipeline\GuardValidationFailure;
use App\Actions\Pipeline\ResolveTemplateStrings;
use App\Actions\Pipeline\SupersedeAnswerChunks;
use App\Models\Configuration;
use Exception;
use Illuminate\Pipeline\Pipeline;

final class CommitConfigurationAnswers
{
    /**
     * @param  array<int, PendingAnswer>  $pendingAnswers
     */
    public function __invoke(Configuration $configuration, array $pendingAnswers, ?string $anchorViewId = null): ConfigurationEvaluation
    {
        $evaluation = new ConfigurationEvaluation($configuration, $pendingAnswers, anchorViewId: $anchorViewId);

        $configuration->load(['relevantAnswers', 'relevantAnswerChunks']);
        $configuration->loadMissing('configurator.rootView');

        if (! $configuration->configurator->rootView) {
            abort(404);
        }

        try {
            return app(Pipeline::class)
                ->send($evaluation)
                ->through([
                    GuardAnchorOverride::class,
                    ApplyFlow::class,
                    CollectAndBatchFetchExternalReferences::class,
                    ApplyCalculation::class,
                    ApplyVisibility::class,
                    ApplyValidation::class,
                    ResolveTemplateStrings::class,
                    GuardValidationFailure::class,
                    SupersedeAnswerChunks::class,
                    CommitAnswers::class,
                ])
                ->thenReturn();
        } catch (Exception) {
            return $evaluation;
        }
    }
}
