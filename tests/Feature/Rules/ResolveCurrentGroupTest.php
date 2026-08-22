<?php

use App\Actions\Rules\ResolveCurrentGroup;
use App\Models\Configuration;
use App\Models\Configurator;
use App\Models\Rule;
use App\Models\View;

it('stays on the first group when nothing has been answered yet', function () {
    $configurator = Configurator::factory()->create();
    $groupOne = View::factory()->group()->create(['configurator_id' => $configurator->id, 'order' => 0]);
    $name = View::factory()->textInput()->childOf($groupOne)->create();
    Rule::factory()->validation("answers['{$name->id}'] != null")->create(['view_id' => $name->id]);
    View::factory()->group()->create(['configurator_id' => $configurator->id, 'order' => 1]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);

    $current = app(ResolveCurrentGroup::class)($configuration);

    expect($current->id)->toBe($groupOne->id);
});

it('advances past a group once it is answered and acknowledged', function () {
    $configurator = Configurator::factory()->create();
    $groupOne = View::factory()->group()->create(['configurator_id' => $configurator->id, 'order' => 0]);
    $groupTwo = View::factory()->group()->create(['configurator_id' => $configurator->id, 'order' => 1]);
    $name = View::factory()->textInput()->childOf($groupOne)->create();
    Rule::factory()->validation("answers['{$name->id}'] != null")->create(['view_id' => $name->id]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);
    answer($configuration, $name, 'Ada');

    $current = app(ResolveCurrentGroup::class)($configuration);

    expect($current->id)->toBe($groupTwo->id);
});

it('stops on a content-only group until it is acknowledged', function () {
    $configurator = Configurator::factory()->create();
    $groupOne = View::factory()->group()->create(['configurator_id' => $configurator->id, 'order' => 0]);
    $groupTwo = View::factory()->group()->create(['configurator_id' => $configurator->id, 'order' => 1]);
    Rule::factory()->validation("answers['{$groupOne->id}'] != null")->create(['view_id' => $groupOne->id]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);

    expect(app(ResolveCurrentGroup::class)($configuration)->id)->toBe($groupOne->id);

    answer($configuration, $groupOne, true);

    expect(app(ResolveCurrentGroup::class)($configuration)->id)->toBe($groupTwo->id);
});

it('stops at the last group once reached (terminal, no next group)', function () {
    $configurator = Configurator::factory()->create();
    $onlyGroup = View::factory()->group()->create(['configurator_id' => $configurator->id, 'order' => 0]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);

    expect(app(ResolveCurrentGroup::class)($configuration)->id)->toBe($onlyGroup->id);
});
