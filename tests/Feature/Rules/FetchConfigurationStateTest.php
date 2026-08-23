<?php

use App\Actions\Rules\FetchConfigurationState;
use App\Models\Answer;
use App\Models\Configuration;
use App\Models\Configurator;
use App\Models\Rule;
use App\Models\View;

it('hides a view whose visibility rule does not fire, and excludes it from validation', function () {
    $configurator = Configurator::factory()->create();
    $group = View::factory()->group()->create(['configurator_id' => $configurator->id]);
    $diet = View::factory()->textInput()->childOf($group)->create();
    $allergy = View::factory()->textInput()->childOf($group)->create();

    Rule::factory()->visibility("answers['{$diet->id}'] == 'vegan'")->create(['view_id' => $allergy->id]);
    Rule::factory()->validation("answers['{$allergy->id}'] != null")->create(['view_id' => $allergy->id]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);
    answer($configuration, $diet, 'omnivore');

    $evaluation = app(FetchConfigurationState::class)($configuration);

    expect($evaluation->componentContexts['visibility'][$allergy->id])->toBeFalse();
    expect($evaluation->componentContexts['validation'])->toBe([]);
});

it('shows a view once its visibility rule fires', function () {
    $configurator = Configurator::factory()->create();
    $group = View::factory()->group()->create(['configurator_id' => $configurator->id]);
    $diet = View::factory()->textInput()->childOf($group)->create();
    $allergy = View::factory()->textInput()->childOf($group)->create();

    Rule::factory()->visibility("answers['{$diet->id}'] == 'vegan'")->create(['view_id' => $allergy->id]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);
    answer($configuration, $diet, 'vegan');

    $evaluation = app(FetchConfigurationState::class)($configuration);

    expect($evaluation->componentContexts['visibility'][$allergy->id])->toBeTrue();
});

it('hides a whole branch when its ancestor is hidden', function () {
    $configurator = Configurator::factory()->create();
    $group = View::factory()->group()->create(['configurator_id' => $configurator->id]);
    $diet = View::factory()->textInput()->childOf($group)->create();
    $veganExtras = View::factory()->group()->create(['configurator_id' => $configurator->id]);
    $content = View::factory()->html('curated menu')->childOf($veganExtras)->create();

    Rule::factory()->visibility("answers['{$diet->id}'] == 'vegan'")->create(['view_id' => $veganExtras->id]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);
    answer($configuration, $diet, 'omnivore');

    $evaluation = app(FetchConfigurationState::class)($configuration);

    expect($evaluation->componentContexts['visibility'][$veganExtras->id])->toBeFalse();
    expect($evaluation->componentContexts['visibility'][$content->id])->toBeFalse();
});

it('previews pending answers without persisting them', function () {
    $configurator = Configurator::factory()->create();
    $group = View::factory()->group()->create(['configurator_id' => $configurator->id]);
    $diet = View::factory()->textInput()->childOf($group)->create();
    $allergy = View::factory()->textInput()->childOf($group)->create();
    Rule::factory()->visibility("answers['{$diet->id}'] == 'vegan'")->create(['view_id' => $allergy->id]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);

    $evaluation = app(FetchConfigurationState::class)($configuration, [$diet->id => 'vegan']);

    expect($evaluation->componentContexts['visibility'][$allergy->id])->toBeTrue();
    expect(Answer::count())->toBe(0);
});

it('reveals only the current unanswered step by default (just_next strategy, depth 0)', function () {
    $configurator = Configurator::factory()->create();
    $groupOne = View::factory()->group()->create(['configurator_id' => $configurator->id, 'order' => 0]);
    $groupTwo = View::factory()->group()->create(['configurator_id' => $configurator->id, 'order' => 1]);
    $name = View::factory()->textInput()->childOf($groupOne)->create();
    Rule::factory()->validation("answers['{$name->id}'] != null")->create(['view_id' => $name->id]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);

    $evaluation = app(FetchConfigurationState::class)($configuration);

    expect($evaluation->componentContexts['scope'])->toContain($groupOne->id, $name->id);
    expect($evaluation->componentContexts['scope'])->not->toContain($groupTwo->id);
});

it('advances scope to the next step only once the current one is persisted', function () {
    $configurator = Configurator::factory()->create();
    $groupOne = View::factory()->group()->create(['configurator_id' => $configurator->id, 'order' => 0]);
    $groupTwo = View::factory()->group()->create(['configurator_id' => $configurator->id, 'order' => 1]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);
    answer($configuration, $groupOne, true);

    $evaluation = app(FetchConfigurationState::class)($configuration);

    expect($evaluation->componentContexts['scope'])->toBe([$groupTwo->id]);
});

it('does not advance scope from a pending answer alone', function () {
    $configurator = Configurator::factory()->create();
    $groupOne = View::factory()->group()->create(['configurator_id' => $configurator->id, 'order' => 0]);
    $groupTwo = View::factory()->group()->create(['configurator_id' => $configurator->id, 'order' => 1]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);

    $evaluation = app(FetchConfigurationState::class)($configuration, [$groupOne->id => true]);

    expect($evaluation->componentContexts['scope'])->toBe([$groupOne->id]);
    expect($evaluation->componentContexts['scope'])->not->toContain($groupTwo->id);
});
