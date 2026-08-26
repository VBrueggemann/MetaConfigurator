<?php

use App\Actions\DTO\ConfigurationEvaluation;
use App\Actions\DTO\PendingAnswer;
use App\Actions\Pipeline\ApplyFlow;
use App\Models\Answer;
use App\Models\Component;
use App\Models\Configuration;
use App\Models\Configurator;
use App\Models\Rule;
use App\Models\View;

function applyFlow(Configuration $configuration, ?string $anchorViewId = null): ConfigurationEvaluation
{
    $evaluation = new ConfigurationEvaluation($configuration, [], anchorViewId: $anchorViewId);

    $configuration->load(['relevantAnswers', 'relevantAnswerChunks']);
    $configuration->loadMissing('configurator.rootView');

    app(ApplyFlow::class)($evaluation, fn (ConfigurationEvaluation $evaluation) => $evaluation);

    return $evaluation;
}

it('anchors at the lowest-order view at the configured depth when nothing is answered yet', function () {
    $configurator = Configurator::factory()->create();
    $root = View::factory()->group()->create(['configurator_id' => $configurator->id]);
    $groupOne = View::factory()->group()->childOf($root)->create(['order' => 0]);
    $groupTwo = View::factory()->group()->childOf($root)->create(['order' => 1]);
    Component::factory()->scope(1)->create(['configurator_id' => $configurator->id]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);

    $evaluation = applyFlow($configuration);

    expect($evaluation->anchorViewId)->toBe($groupOne->id);
});

it('advances via an explicit Flow rule referencing a persisted answer', function () {
    $configurator = Configurator::factory()->create();
    $root = View::factory()->group()->create(['configurator_id' => $configurator->id]);
    $groupOne = View::factory()->group()->childOf($root)->create(['order' => 0]);
    $color = View::factory()->textInput()->childOf($groupOne)->create(['order' => 0]);
    $groupTwo = View::factory()->group()->childOf($root)->create(['order' => 1]);
    $groupThree = View::factory()->group()->childOf($root)->create(['order' => 2]);
    Rule::factory()->flow("answer('{$color->id}') == 'red'", $groupThree->id)->create(['view_id' => $groupOne->id]);
    Component::factory()->scope(1)->create(['configurator_id' => $configurator->id]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);
    answer($configuration, $groupOne, true);
    Answer::factory()->create(['configuration_id' => $configuration->id, 'view_id' => $color->id, 'value' => 'red']);

    $evaluation = applyFlow($configuration);

    expect($evaluation->anchorViewId)->toBe($groupThree->id);
});

it('bounds the flow-rule scan to the last-answered view, never reconsidering an earlier one', function () {
    $configurator = Configurator::factory()->create();
    $root = View::factory()->group()->create(['configurator_id' => $configurator->id]);
    $groupOne = View::factory()->group()->childOf($root)->create(['order' => 0]);
    $groupTwo = View::factory()->group()->childOf($root)->create(['order' => 1]);
    $groupThree = View::factory()->group()->childOf($root)->create(['order' => 2]);
    $groupFour = View::factory()->group()->childOf($root)->create(['order' => 3]);
    Rule::factory()->flow('true', $groupFour->id)->create(['view_id' => $groupOne->id]);
    Component::factory()->scope(1)->create(['configurator_id' => $configurator->id]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);
    answer($configuration, $groupTwo, true);

    $evaluation = applyFlow($configuration);

    expect($evaluation->anchorViewId)->toBe($groupThree->id);
});

it('ignores a pending answer when evaluating Flow rules, using only persisted state', function () {
    $configurator = Configurator::factory()->create();
    $root = View::factory()->group()->create(['configurator_id' => $configurator->id]);
    $groupOne = View::factory()->group()->childOf($root)->create(['order' => 0]);
    $groupTwo = View::factory()->group()->childOf($root)->create(['order' => 1]);
    Rule::factory()->flow("answer('{$groupOne->id}') == true", $groupTwo->id)->create(['view_id' => $groupOne->id]);
    Component::factory()->scope(1)->create(['configurator_id' => $configurator->id]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);
    $evaluation = new ConfigurationEvaluation($configuration, [new PendingAnswer($groupOne->id, true)]);
    $configuration->load(['relevantAnswers', 'relevantAnswerChunks']);
    $configuration->loadMissing('configurator.rootView');

    app(ApplyFlow::class)($evaluation, fn (ConfigurationEvaluation $evaluation) => $evaluation);

    expect($evaluation->anchorViewId)->toBe($groupOne->id);
});
