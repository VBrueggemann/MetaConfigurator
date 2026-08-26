<?php

use App\Actions\DTO\ConfigurationEvaluation;
use App\Actions\DTO\PendingAnswer;
use App\Actions\Pipeline\ApplyFlow;
use App\Actions\Pipeline\ApplyVisibility;
use App\Models\Component;
use App\Models\Configuration;
use App\Models\Configurator;
use App\Models\Rule;
use App\Models\View;

/**
 * @param  array<string, mixed>  $pendingAnswers
 */
function applyVisibility(Configuration $configuration, array $pendingAnswers = []): ConfigurationEvaluation
{
    $evaluation = new ConfigurationEvaluation($configuration, array_map(
        fn (string $viewId, mixed $value): PendingAnswer => new PendingAnswer($viewId, $value),
        array_keys($pendingAnswers),
        array_values($pendingAnswers),
    ));

    $configuration->load(['relevantAnswers', 'relevantAnswerChunks']);
    $configuration->loadMissing('configurator.rootView');

    app(ApplyFlow::class)($evaluation, fn (ConfigurationEvaluation $evaluation) => $evaluation);
    app(ApplyVisibility::class)($evaluation, fn (ConfigurationEvaluation $evaluation) => $evaluation);

    return $evaluation;
}

it('prunes an invisible view and its descendants from the scope tree', function () {
    $configurator = Configurator::factory()->create();
    $root = View::factory()->group()->create(['configurator_id' => $configurator->id]);
    $childA = View::factory()->group()->childOf($root)->create(['order' => 0]);
    $childB = View::factory()->group()->childOf($root)->create(['order' => 1]);
    $grandchild = View::factory()->textInput()->childOf($childB)->create(['order' => 0]);
    Rule::factory()->visibility('false')->create(['view_id' => $childB->id]);
    Component::factory()->scope(0)->create(['configurator_id' => $configurator->id]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);

    $evaluation = applyVisibility($configuration);

    expect(scopeIds($evaluation))->toContain($root->id, $childA->id);
    expect(scopeIds($evaluation))->not->toContain($childB->id, $grandchild->id);
});

it('no longer exposes a separate componentContexts visibility side-map', function () {
    $configurator = Configurator::factory()->create();
    $root = View::factory()->group()->create(['configurator_id' => $configurator->id]);
    Component::factory()->scope(0)->create(['configurator_id' => $configurator->id]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);

    $evaluation = applyVisibility($configuration);

    expect($evaluation->componentContexts)->not->toHaveKey('visibility');
});

it('still filters out pending answers for invisible views', function () {
    $configurator = Configurator::factory()->create();
    $root = View::factory()->group()->create(['configurator_id' => $configurator->id]);
    $hidden = View::factory()->textInput()->childOf($root)->create(['order' => 0]);
    Rule::factory()->visibility('false')->create(['view_id' => $hidden->id]);
    Component::factory()->scope(0)->create(['configurator_id' => $configurator->id]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);

    $evaluation = applyVisibility($configuration, [$hidden->id => 'x']);

    expect($evaluation->pendingAnswers)->toBeEmpty();
});
