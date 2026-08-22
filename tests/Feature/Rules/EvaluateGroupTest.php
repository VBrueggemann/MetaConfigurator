<?php

use App\Actions\Rules\EvaluateGroup;
use App\Models\Answer;
use App\Models\Configuration;
use App\Models\Configurator;
use App\Models\Rule;
use App\Models\View;

function makeConfigurator(): Configurator
{
    return Configurator::factory()->create();
}

it('hides a view whose visibility rule does not fire, and excludes it from validation', function () {
    $configurator = makeConfigurator();
    $group = View::factory()->group()->create(['configurator_id' => $configurator->id]);
    $diet = View::factory()->textInput()->childOf($group)->create();
    $allergy = View::factory()->textInput()->childOf($group)->create();

    Rule::factory()->create([
        'view_id' => $allergy->id,
        'component' => 'visibility',
        'expression' => "answers['{$diet->id}'] == 'vegan'",
    ]);
    // Even though hidden, give it a validation rule that would otherwise fail.
    Rule::factory()->create([
        'view_id' => $allergy->id,
        'component' => 'validation',
        'expression' => "answers['{$allergy->id}'] != null",
    ]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);
    Answer::factory()->create(['configuration_id' => $configuration->id, 'view_id' => $diet->id, 'value' => 'omnivore']);

    $evaluation = app(EvaluateGroup::class)($group, $configuration);

    expect($evaluation->componentContexts['visibility'])->toBe([$diet->id]);
    expect($evaluation->componentContexts['validation'])->toBe([]);
});

it('shows a view once its visibility rule fires', function () {
    $configurator = makeConfigurator();
    $group = View::factory()->group()->create(['configurator_id' => $configurator->id]);
    $diet = View::factory()->textInput()->childOf($group)->create();
    $allergy = View::factory()->textInput()->childOf($group)->create();

    Rule::factory()->create([
        'view_id' => $allergy->id,
        'component' => 'visibility',
        'expression' => "answers['{$diet->id}'] == 'vegan'",
    ]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);
    Answer::factory()->create(['configuration_id' => $configuration->id, 'view_id' => $diet->id, 'value' => 'vegan']);

    $evaluation = app(EvaluateGroup::class)($group, $configuration);

    expect($evaluation->componentContexts['visibility'])->toContain($diet->id, $allergy->id);
});

it('records a validation error when a validation rule fails, and blocks next-group resolution', function () {
    $configurator = makeConfigurator();
    $group = View::factory()->group()->create(['configurator_id' => $configurator->id]);
    $name = View::factory()->textInput()->childOf($group)->create();

    Rule::factory()->create([
        'view_id' => $name->id,
        'component' => 'validation',
        'expression' => "answers['{$name->id}'] != null",
    ]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);

    $evaluation = app(EvaluateGroup::class)($group, $configuration);

    expect($evaluation->componentContexts['validation'])->toHaveKey($name->id);
    expect($evaluation->componentContexts['next_group'])->toBeNull();
});

it('falls back to the sequential next group when no next-group rule fires', function () {
    $configurator = makeConfigurator();
    $groupOne = View::factory()->group()->create(['configurator_id' => $configurator->id, 'order' => 0]);
    $groupTwo = View::factory()->group()->create(['configurator_id' => $configurator->id, 'order' => 1]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);

    $evaluation = app(EvaluateGroup::class)($groupOne, $configuration);

    expect($evaluation->componentContexts['next_group'])->toBe($groupTwo->id);
});

it('takes the first firing next-group rule, in order, over the sequential fallback', function () {
    $configurator = makeConfigurator();
    $groupOne = View::factory()->group()->create(['configurator_id' => $configurator->id, 'order' => 0]);
    $groupTwo = View::factory()->group()->create(['configurator_id' => $configurator->id, 'order' => 1]);
    $vipGroup = View::factory()->group()->create(['configurator_id' => $configurator->id, 'order' => 2]);
    $diet = View::factory()->textInput()->childOf($groupOne)->create();

    Rule::factory()->create([
        'view_id' => $groupOne->id,
        'component' => 'next_group',
        'expression' => 'false',
        'meta' => ['next_view_id' => $groupTwo->id],
        'order' => 0,
    ]);
    Rule::factory()->create([
        'view_id' => $groupOne->id,
        'component' => 'next_group',
        'expression' => "answers['{$diet->id}'] == 'vip'",
        'meta' => ['next_view_id' => $vipGroup->id],
        'order' => 1,
    ]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);
    Answer::factory()->create(['configuration_id' => $configuration->id, 'view_id' => $diet->id, 'value' => 'vip']);

    $evaluation = app(EvaluateGroup::class)($groupOne, $configuration);

    expect($evaluation->componentContexts['next_group'])->toBe($vipGroup->id);
});
