<?php

namespace App\Actions;

use App\Actions\DTO\ConfigurationEvaluation;
use App\Actions\DTO\PendingAnswer;
use App\Actions\Pipeline\ApplyValidation;
use App\Actions\Pipeline\CommitAnswers;
use App\Actions\Pipeline\SupersedeAnswerChunks;
use App\Enums\ComponentType;
use App\Exceptions\ValidationFailedException;
use App\Models\Configuration;
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
                    ...FetchConfigurationState::PREFIX,
                    app(ApplyValidation::class, ['requireVisible' => true, 'throwOnFailure' => true]),
                    SupersedeAnswerChunks::class,
                    CommitAnswers::class,
                ])
                ->thenReturn();
        } catch (ValidationFailedException $e) {
            $evaluation->componentContexts[ComponentType::Validation->value] = $e->errors;

            return $evaluation;
        }
    }
}
