<?php

use App\Actions\DTO\PendingAnswer;
use App\Actions\FetchConfigurationState;
use App\Models\Answer;
use App\Models\Component;
use App\Models\Configuration;
use App\Models\Configurator;
use App\Models\Rule;
use App\Models\View;

it('hides a view whose visibility rule does not fire, and excludes it from validation', function () {
    $configurator = Configurator::factory()->create();
    $group = View::factory()->group()->create(['configurator_id' => $configurator->id]);
    $diet = View::factory()->textInput()->childOf($group)->create();
    $allergy = View::factory()->textInput()->childOf($group)->create();

    Rule::factory()->visibility("answer('{$diet->id}') == 'vegan'")->create(['view_id' => $allergy->id]);
    Rule::factory()->validation("answer('{$allergy->id}') != null")->create(['view_id' => $allergy->id]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);
    answer($configuration, $diet, 'omnivore');

    $evaluation = app(FetchConfigurationState::class)($configuration);

    expect(scopeIds($evaluation))->not->toContain($allergy->id);
});

it('shows a view once its visibility rule fires', function () {
    $configurator = Configurator::factory()->create();
    $group = View::factory()->group()->create(['configurator_id' => $configurator->id]);
    $diet = View::factory()->textInput()->childOf($group)->create();
    $allergy = View::factory()->textInput()->childOf($group)->create();

    Rule::factory()->visibility("answer('{$diet->id}') == 'vegan'")->create(['view_id' => $allergy->id]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);
    answer($configuration, $diet, 'vegan');

    $evaluation = app(FetchConfigurationState::class)($configuration);

    expect(scopeIds($evaluation))->toContain($allergy->id);
});

it('hides a whole branch when its ancestor is hidden', function () {
    $configurator = Configurator::factory()->create();
    $root = View::factory()->group()->create(['configurator_id' => $configurator->id]);
    $diet = View::factory()->textInput()->childOf($root)->create();
    $veganExtras = View::factory()->group()->childOf($root)->create();
    $content = View::factory()->html('curated menu')->childOf($veganExtras)->create();

    Rule::factory()->visibility("answer('{$diet->id}') == 'vegan'")->create(['view_id' => $veganExtras->id]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);
    answer($configuration, $diet, 'omnivore');

    $evaluation = app(FetchConfigurationState::class)($configuration);

    expect(scopeIds($evaluation))->not->toContain($veganExtras->id, $content->id);
});

it('previews pending answers without persisting them', function () {
    $configurator = Configurator::factory()->create();
    $group = View::factory()->group()->create(['configurator_id' => $configurator->id]);
    $diet = View::factory()->textInput()->childOf($group)->create();
    $allergy = View::factory()->textInput()->childOf($group)->create();
    Rule::factory()->visibility("answer('{$diet->id}') == 'vegan'")->create(['view_id' => $allergy->id]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);

    $evaluation = app(FetchConfigurationState::class)($configuration, [new PendingAnswer($diet->id, 'vegan')]);

    expect(scopeIds($evaluation))->toContain($allergy->id);
    expect(Answer::count())->toBe(0);
});

it('reveals only the current unanswered step (just_next strategy)', function () {
    $configurator = Configurator::factory()->create();
    $root = View::factory()->group()->create(['configurator_id' => $configurator->id]);
    $groupOne = View::factory()->group()->childOf($root)->create(['order' => 0]);
    $groupTwo = View::factory()->group()->childOf($root)->create(['order' => 1]);
    $name = View::factory()->textInput()->childOf($groupOne)->create();
    Rule::factory()->validation("answer('{$name->id}') != null")->create(['view_id' => $name->id]);
    Component::factory()->scope(1)->create(['configurator_id' => $configurator->id]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);

    $evaluation = app(FetchConfigurationState::class)($configuration);

    expect(scopeIds($evaluation))->toContain($groupOne->id, $name->id);
    expect(scopeIds($evaluation))->not->toContain($groupTwo->id);
});

it('advances scope to the next step once the current one has any answer', function () {
    $configurator = Configurator::factory()->create();
    $root = View::factory()->group()->create(['configurator_id' => $configurator->id]);
    $groupOne = View::factory()->group()->childOf($root)->create(['order' => 0]);
    $groupTwo = View::factory()->group()->childOf($root)->create(['order' => 1]);
    Component::factory()->scope(1)->create(['configurator_id' => $configurator->id]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);
    answer($configuration, $groupOne, true);

    $evaluation = app(FetchConfigurationState::class)($configuration);

    expect(scopeIds($evaluation))->toEqualCanonicalizing([$root->id, $groupTwo->id]);
});

it('anchors scope at an explicitly requested view instead of the computed next step', function () {
    $configurator = Configurator::factory()->create();
    $root = View::factory()->group()->create(['configurator_id' => $configurator->id]);
    $groupOne = View::factory()->group()->childOf($root)->create(['order' => 0]);
    $groupTwo = View::factory()->group()->childOf($root)->create(['order' => 1]);
    Component::factory()->scope(depth: 1)->create(['configurator_id' => $configurator->id]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);
    answer($configuration, $groupOne, true);
    answer($configuration, $groupTwo, true);

    $evaluation = app(FetchConfigurationState::class)($configuration, [], $groupOne->id);

    expect($evaluation->anchorViewId)->toBe($groupOne->id);
});

it('ignores a requested anchor that is not an existing relevant chunk, falling back to the computed next step', function () {
    $configurator = Configurator::factory()->create();
    $root = View::factory()->group()->create(['configurator_id' => $configurator->id]);
    $groupOne = View::factory()->group()->childOf($root)->create(['order' => 0]);
    $groupTwo = View::factory()->group()->childOf($root)->create(['order' => 1]);
    Component::factory()->scope(depth: 1)->create(['configurator_id' => $configurator->id]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);

    $evaluation = app(FetchConfigurationState::class)($configuration, [], $groupTwo->id);

    expect($evaluation->anchorViewId)->toBe($groupOne->id);
});

it('does not validate a pending answer on a currently hidden view, since it is pruned from scope before validation runs', function () {
    $configurator = Configurator::factory()->create();
    $group = View::factory()->group()->create(['configurator_id' => $configurator->id]);
    $diet = View::factory()->textInput()->childOf($group)->create();
    $allergy = View::factory()->textInput()->childOf($group)->create();

    Rule::factory()->visibility("answer('{$diet->id}') == 'vegan'")->create(['view_id' => $allergy->id]);
    Rule::factory()->validation("answer('{$allergy->id}') != null")->create(['view_id' => $allergy->id]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);
    answer($configuration, $diet, 'omnivore');

    $evaluation = app(FetchConfigurationState::class)($configuration, [new PendingAnswer($allergy->id, null)]);

    expect(scopeIds($evaluation))->not->toContain($allergy->id);
});

it('does not advance scope from a pending answer alone', function () {
    $configurator = Configurator::factory()->create();
    $root = View::factory()->group()->create(['configurator_id' => $configurator->id]);
    $groupOne = View::factory()->group()->childOf($root)->create(['order' => 0]);
    $groupTwo = View::factory()->group()->childOf($root)->create(['order' => 1]);
    Component::factory()->scope(1)->create(['configurator_id' => $configurator->id]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);

    $evaluation = app(FetchConfigurationState::class)($configuration, [new PendingAnswer($groupOne->id, true)]);

    expect(scopeIds($evaluation))->toEqualCanonicalizing([$root->id, $groupOne->id]);
    expect(scopeIds($evaluation))->not->toContain($groupTwo->id);
});
