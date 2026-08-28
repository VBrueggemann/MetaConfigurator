<?php

use App\Actions\DTO\ConfigurationEvaluation;
use App\Actions\DTO\PendingAnswer;
use App\Actions\Pipeline\ApplyFlow;
use App\Actions\Pipeline\ApplyValidation;
use App\Actions\Pipeline\CollectAndBatchFetchExternalReferences;
use App\Models\Configuration;
use App\Models\Configurator;
use App\Models\Rule;
use App\Models\View;

/**
 * @param  array<string, mixed>  $pendingAnswers
 */
function applyValidation(Configuration $configuration, array $pendingAnswers = []): ConfigurationEvaluation
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

    return $evaluation;
}

it('prunes a passing validation rule off a pending view', function () {
    $configurator = Configurator::factory()->create();
    $root = View::factory()->group()->create(['configurator_id' => $configurator->id]);
    $field = View::factory()->textInput()->childOf($root)->create(['order' => 0]);
    Rule::factory()->validation('true')->create(['view_id' => $field->id]);
    $configurator->update(['scope_depth' => 0]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);

    $evaluation = applyValidation($configuration, [$field->id => 'ok']);

    expect(remainingValidationRules($evaluation, $field->id))->toBeEmpty();
});

it('keeps a failing validation rule on a pending view', function () {
    $configurator = Configurator::factory()->create();
    $root = View::factory()->group()->create(['configurator_id' => $configurator->id]);
    $field = View::factory()->textInput()->childOf($root)->create(['order' => 0]);
    Rule::factory()->validation('false')->create(['view_id' => $field->id, 'meta' => ['message' => 'invalid']]);
    $configurator->update(['scope_depth' => 0]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);

    $evaluation = applyValidation($configuration, [$field->id => 'bad']);

    expect(remainingValidationRules($evaluation, $field->id)->pluck('meta.message')->all())->toBe(['invalid']);
});

it('removes even a failing validation rule from a view that was not pending', function () {
    $configurator = Configurator::factory()->create();
    $root = View::factory()->group()->create(['configurator_id' => $configurator->id]);
    $field = View::factory()->textInput()->childOf($root)->create(['order' => 0]);
    Rule::factory()->validation('false')->create(['view_id' => $field->id]);
    $configurator->update(['scope_depth' => 0]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);

    $evaluation = applyValidation($configuration, []);

    expect(remainingValidationRules($evaluation, $field->id))->toBeEmpty();
});

it('treats a group as pending when a descendant leaf is pending', function () {
    $configurator = Configurator::factory()->create();
    $root = View::factory()->group()->create(['configurator_id' => $configurator->id]);
    $group = View::factory()->group()->childOf($root)->create(['order' => 0]);
    $field = View::factory()->textInput()->childOf($group)->create(['order' => 0]);
    Rule::factory()->validation('false')->create(['view_id' => $group->id, 'meta' => ['message' => 'group invalid']]);
    $configurator->update(['scope_depth' => 0]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);

    $evaluation = applyValidation($configuration, [$field->id => 'x']);

    expect(remainingValidationRules($evaluation, $group->id)->pluck('meta.message')->all())->toBe(['group invalid']);
});
