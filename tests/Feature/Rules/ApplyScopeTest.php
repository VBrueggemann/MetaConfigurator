<?php

use App\Actions\Rules\FetchConfigurationState;
use App\Models\Component;
use App\Models\Configuration;
use App\Models\Configurator;
use App\Models\Rule;
use App\Models\View;

it('reveals every visible step under the "all" strategy', function () {
    $configurator = Configurator::factory()->create();
    $groupOne = View::factory()->group()->create(['configurator_id' => $configurator->id, 'order' => 0]);
    $groupTwo = View::factory()->group()->create(['configurator_id' => $configurator->id, 'order' => 1]);
    Component::factory()->scope('all', 0)->create(['configurator_id' => $configurator->id]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);

    $evaluation = app(FetchConfigurationState::class)($configuration);

    expect($evaluation->componentContexts['scope'])->toContain($groupOne->id, $groupTwo->id);
});

it('reveals the answered prefix plus n under the "answered_plus_n" strategy', function () {
    $configurator = Configurator::factory()->create();
    $groupOne = View::factory()->group()->create(['configurator_id' => $configurator->id, 'order' => 0]);
    $groupTwo = View::factory()->group()->create(['configurator_id' => $configurator->id, 'order' => 1]);
    $groupThree = View::factory()->group()->create(['configurator_id' => $configurator->id, 'order' => 2]);
    Component::factory()->scope('answered_plus_n', 0, ['n' => 1])->create(['configurator_id' => $configurator->id]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);
    answer($configuration, $groupOne, true);

    $evaluation = app(FetchConfigurationState::class)($configuration);

    expect($evaluation->componentContexts['scope'])->toContain($groupOne->id, $groupTwo->id);
    expect($evaluation->componentContexts['scope'])->not->toContain($groupThree->id);
});

it('skips a hidden step when walking for completeness, in place of explicit branching', function () {
    $configurator = Configurator::factory()->create();
    $groupOne = View::factory()->group()->create(['configurator_id' => $configurator->id, 'order' => 0]);
    $diet = View::factory()->textInput()->childOf($groupOne)->create();
    $groupTwo = View::factory()->group()->create(['configurator_id' => $configurator->id, 'order' => 1]);
    $groupThree = View::factory()->group()->create(['configurator_id' => $configurator->id, 'order' => 2]);
    Rule::factory()->visibility("answers['{$diet->id}'] != 'vegan'")->create(['view_id' => $groupTwo->id]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);
    answer($configuration, $diet, 'vegan');
    answer($configuration, $groupOne, true);

    $evaluation = app(FetchConfigurationState::class)($configuration);

    expect($evaluation->componentContexts['scope'])->toBe([$groupThree->id]);
});

it('treats a step as complete only once it is both answered and validation-error-free', function () {
    $configurator = Configurator::factory()->create();
    $groupOne = View::factory()->group()->create(['configurator_id' => $configurator->id, 'order' => 0]);
    $name = View::factory()->textInput()->childOf($groupOne)->create();
    $groupTwo = View::factory()->group()->create(['configurator_id' => $configurator->id, 'order' => 1]);
    Rule::factory()->validation("answers['{$name->id}'] != null")->create(['view_id' => $name->id]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);
    answer($configuration, $groupOne, true);

    $evaluation = app(FetchConfigurationState::class)($configuration);

    expect($evaluation->componentContexts['scope'])->toContain($groupOne->id);
    expect($evaluation->componentContexts['scope'])->not->toContain($groupTwo->id);
});

it('treats deeper nesting than the configured depth as sub-fields of one step', function () {
    $configurator = Configurator::factory()->create();
    $section = View::factory()->group()->create(['configurator_id' => $configurator->id, 'order' => 0]);
    $subsection = View::factory()->group()->childOf($section)->create(['order' => 0]);
    $question = View::factory()->textInput()->childOf($subsection)->create(['order' => 0]);
    Component::factory()->scope('all', 1)->create(['configurator_id' => $configurator->id]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);

    $evaluation = app(FetchConfigurationState::class)($configuration);

    expect($evaluation->componentContexts['scope'])->toContain($subsection->id, $question->id);
    expect($evaluation->componentContexts['scope'])->not->toContain($section->id);
});
