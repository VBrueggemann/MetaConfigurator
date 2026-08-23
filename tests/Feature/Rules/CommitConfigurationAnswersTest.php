<?php

use App\Actions\DTO\PendingAnswer;
use App\Actions\Rules\CommitConfigurationAnswers;
use App\Models\Answer;
use App\Models\Configuration;
use App\Models\Configurator;
use App\Models\Rule;
use App\Models\View;

it('persists pending answers when validation passes', function () {
    $configurator = Configurator::factory()->create();
    $group = View::factory()->group()->create(['configurator_id' => $configurator->id]);
    $name = View::factory()->textInput()->childOf($group)->create();
    Rule::factory()->validation("answers['{$name->id}'] != null")->create(['view_id' => $name->id]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);

    $evaluation = app(CommitConfigurationAnswers::class)($configuration, [new PendingAnswer($group->id, true), new PendingAnswer($name->id, 'Ada')]);

    expect($evaluation->componentContexts['validation'])->toBe([]);
    expect(Answer::where('configuration_id', $configuration->id)->where('view_id', $name->id)->first()->value)->toBe('Ada');
});

it('persists nothing when any pending answer is invalid (all-or-nothing)', function () {
    $configurator = Configurator::factory()->create();
    $group = View::factory()->group()->create(['configurator_id' => $configurator->id]);
    $name = View::factory()->textInput()->childOf($group)->create();
    $diet = View::factory()->textInput()->childOf($group)->create();
    Rule::factory()->validation("answers['{$name->id}'] != null")->create(['view_id' => $name->id]);
    Rule::factory()->validation("answers['{$diet->id}'] != null")->create(['view_id' => $diet->id]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);

    $evaluation = app(CommitConfigurationAnswers::class)($configuration, [
        new PendingAnswer($group->id, true),
        new PendingAnswer($name->id, 'Ada'),
        new PendingAnswer($diet->id, null),
    ]);

    expect($evaluation->componentContexts['validation'])->toHaveKey($diet->id);
    expect(Answer::count())->toBe(0);
});

it('re-validates on commit even without a prior fetch', function () {
    $configurator = Configurator::factory()->create();
    $group = View::factory()->group()->create(['configurator_id' => $configurator->id]);
    $name = View::factory()->textInput()->childOf($group)->create();
    Rule::factory()->validation("answers['{$name->id}'] != null")->create(['view_id' => $name->id]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);

    $evaluation = app(CommitConfigurationAnswers::class)($configuration, [new PendingAnswer($group->id, true)]);

    expect($evaluation->componentContexts['validation'])->toHaveKey($name->id);
    expect(Answer::count())->toBe(0);
});
