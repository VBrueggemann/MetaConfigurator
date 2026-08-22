<?php

use App\Actions\Rules\BuildAnswerContext;
use App\Models\Answer;
use App\Models\Configuration;
use App\Models\Configurator;
use App\Models\View;

it('builds an answers map keyed by view id from the configuration\'s current answers', function () {
    $configurator = Configurator::factory()->create();
    $name = View::factory()->textInput()->create(['configurator_id' => $configurator->id]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);
    Answer::factory()->create([
        'configuration_id' => $configuration->id,
        'view_id' => $name->id,
        'value' => 'Ada',
    ]);

    $context = app(BuildAnswerContext::class)($configuration);

    expect($context)->toBe(['answers' => [$name->id => 'Ada']]);
});

it('returns an empty answers map when nothing has been answered yet', function () {
    $configuration = Configuration::factory()->create();

    $context = app(BuildAnswerContext::class)($configuration);

    expect($context)->toBe(['answers' => []]);
});

it('nests answers under their parent group id, as well as flat by view id', function () {
    $configurator = Configurator::factory()->create();
    $groupOne = View::factory()->group()->create(['configurator_id' => $configurator->id]);
    $name = View::factory()->textInput()->childOf($groupOne)->create();
    $groupTwo = View::factory()->group()->create(['configurator_id' => $configurator->id]);
    $diet = View::factory()->select([['value' => 'veg', 'label' => 'Veg']])->childOf($groupTwo)->create();

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);
    Answer::factory()->create(['configuration_id' => $configuration->id, 'view_id' => $name->id, 'value' => 'Ada']);
    Answer::factory()->create(['configuration_id' => $configuration->id, 'view_id' => $diet->id, 'value' => 'veg']);

    $context = app(BuildAnswerContext::class)($configuration);

    expect($context)->toBe(['answers' => [
        $name->id => 'Ada',
        $groupOne->id => [$name->id => 'Ada'],
        $diet->id => 'veg',
        $groupTwo->id => [$diet->id => 'veg'],
    ]]);
});
