<?php

use App\Actions\DTO\ConfigurationEvaluation;
use App\Actions\DTO\PendingAnswer;
use App\Actions\Pipeline\ApplyFlow;
use App\Actions\Pipeline\ApplyValidation;
use App\Actions\Pipeline\CollectAndBatchFetchExternalReferences;
use App\Actions\Pipeline\GuardValidationFailure;
use App\Exceptions\ValidationFailedException;
use App\Models\Component;
use App\Models\Configuration;
use App\Models\Configurator;
use App\Models\Rule;
use App\Models\View;

/**
 * @param  array<string, mixed>  $pendingAnswers
 */
function guardValidationFailure(Configuration $configuration, array $pendingAnswers = []): ConfigurationEvaluation
{
    $evaluation = new ConfigurationEvaluation($configuration, array_map(
        fn (string $viewId, mixed $value): PendingAnswer => new PendingAnswer($viewId, $value),
        array_keys($pendingAnswers),
        array_values($pendingAnswers),
    ));

    $configuration->load(['relevantAnswers', 'relevantAnswerChunks']);
    $configuration->loadMissing('configurator.rootView');

    app(ApplyFlow::class)($evaluation, fn (ConfigurationEvaluation $evaluation) => $evaluation);
    app(CollectAndBatchFetchExternalReferences::class)($evaluation, fn (ConfigurationEvaluation $evaluation) => $evaluation);
    app(ApplyValidation::class)($evaluation, fn (ConfigurationEvaluation $evaluation) => $evaluation);
    app(GuardValidationFailure::class)($evaluation, fn (ConfigurationEvaluation $evaluation) => $evaluation);

    return $evaluation;
}

it('throws ValidationFailedException when a failing rule remains', function () {
    $configurator = Configurator::factory()->create();
    $root = View::factory()->group()->create(['configurator_id' => $configurator->id]);
    $field = View::factory()->textInput()->childOf($root)->create(['order' => 0]);
    Rule::factory()->validation('false')->create(['view_id' => $field->id]);
    Component::factory()->scope(0)->create(['configurator_id' => $configurator->id]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);

    guardValidationFailure($configuration, [$field->id => 'bad']);
})->throws(ValidationFailedException::class);

it('does not throw when nothing fails', function () {
    $configurator = Configurator::factory()->create();
    $root = View::factory()->group()->create(['configurator_id' => $configurator->id]);
    $field = View::factory()->textInput()->childOf($root)->create(['order' => 0]);
    Rule::factory()->validation('true')->create(['view_id' => $field->id]);
    Component::factory()->scope(0)->create(['configurator_id' => $configurator->id]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);

    $evaluation = guardValidationFailure($configuration, [$field->id => 'ok']);

    expect($evaluation)->not->toBeNull();
});
