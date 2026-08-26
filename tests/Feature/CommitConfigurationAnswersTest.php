<?php

use App\Actions\CommitConfigurationAnswers;
use App\Actions\DTO\PendingAnswer;
use App\Models\Answer;
use App\Models\AnswerChunk;
use App\Models\Component;
use App\Models\Configuration;
use App\Models\Configurator;
use App\Models\Rule;
use App\Models\View;

it('wraps a commit\'s answers in a new relevant chunk anchored at the committed step', function () {
    $configurator = Configurator::factory()->create();
    $group = View::factory()->group()->create(['configurator_id' => $configurator->id]);
    $name = View::factory()->textInput()->childOf($group)->create();

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);

    app(CommitConfigurationAnswers::class)($configuration, [new PendingAnswer($name->id, 'Ada')]);

    $chunk = AnswerChunk::sole();
    expect($chunk->configuration_id)->toBe($configuration->id);
    expect($chunk->anchor_view_id)->toBe($group->id);
    expect($chunk->relevant)->toBeTrue();
    expect($chunk->sequence)->toBe(1);
    expect(Answer::where('view_id', $name->id)->first()->answer_chunk_id)->toBe($chunk->id);
});

it('appends a second chunk with the next sequence for a later commit', function () {
    $configurator = Configurator::factory()->create();
    $root = View::factory()->group()->create(['configurator_id' => $configurator->id]);
    $groupOne = View::factory()->group()->childOf($root)->create(['order' => 0]);
    $groupTwo = View::factory()->group()->childOf($root)->create(['order' => 1]);
    $name = View::factory()->textInput()->childOf($groupTwo)->create();
    Component::factory()->scope(depth: 1)->create(['configurator_id' => $configurator->id]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);

    app(CommitConfigurationAnswers::class)($configuration, []);
    app(CommitConfigurationAnswers::class)($configuration, [new PendingAnswer($name->id, 'Ada')]);

    expect(AnswerChunk::count())->toBe(2);
    expect(AnswerChunk::orderBy('sequence')->pluck('anchor_view_id')->all())->toBe([$groupOne->id, $groupTwo->id]);
    expect(AnswerChunk::orderBy('sequence')->pluck('sequence')->all())->toBe([1, 2]);
});

it('supersedes the current chunk and everything after it when revisiting an already-answered step', function () {
    $configurator = Configurator::factory()->create();
    $root = View::factory()->group()->create(['configurator_id' => $configurator->id]);
    $groupOne = View::factory()->group()->childOf($root)->create(['order' => 0]);
    $groupTwo = View::factory()->group()->childOf($root)->create(['order' => 1]);
    $groupThree = View::factory()->group()->childOf($root)->create(['order' => 2]);
    Component::factory()->scope(depth: 1)->create(['configurator_id' => $configurator->id]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);

    app(CommitConfigurationAnswers::class)($configuration, []);
    app(CommitConfigurationAnswers::class)($configuration, []);
    app(CommitConfigurationAnswers::class)($configuration, []);

    expect(AnswerChunk::where('relevant', true)->count())->toBe(3);

    app(CommitConfigurationAnswers::class)($configuration, [], $groupTwo->id);

    $relevant = AnswerChunk::where('relevant', true)->orderBy('sequence')->get();
    expect($relevant->pluck('anchor_view_id')->all())->toBe([$groupOne->id, $groupTwo->id]);

    $superseded = AnswerChunk::where('relevant', false)->orderBy('sequence')->get();
    expect($superseded->pluck('anchor_view_id')->all())->toBe([$groupTwo->id, $groupThree->id]);
});

it('ignores a requested anchor that is neither the computed next step nor an existing relevant chunk when committing', function () {
    $configurator = Configurator::factory()->create();
    $root = View::factory()->group()->create(['configurator_id' => $configurator->id]);
    $groupOne = View::factory()->group()->childOf($root)->create(['order' => 0]);
    $groupTwo = View::factory()->group()->childOf($root)->create(['order' => 1]);
    Component::factory()->scope(depth: 1)->create(['configurator_id' => $configurator->id]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);

    app(CommitConfigurationAnswers::class)($configuration, [], $groupTwo->id);

    expect(AnswerChunk::sole()->anchor_view_id)->toBe($groupOne->id);
});

it('persists pending answers when validation passes', function () {
    $configurator = Configurator::factory()->create();
    $group = View::factory()->group()->create(['configurator_id' => $configurator->id]);
    $name = View::factory()->textInput()->childOf($group)->create();
    Rule::factory()->validation("answer('{$name->id}') != null")->create(['view_id' => $name->id]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);

    $evaluation = app(CommitConfigurationAnswers::class)($configuration, [new PendingAnswer($group->id, true), new PendingAnswer($name->id, 'Ada')]);

    expect(remainingValidationRules($evaluation, $name->id))->toBeEmpty();
    expect(Answer::where('configuration_id', $configuration->id)->where('view_id', $name->id)->first()->value)->toBe('Ada');
});

it('persists nothing when any pending answer is invalid (all-or-nothing)', function () {
    $configurator = Configurator::factory()->create();
    $group = View::factory()->group()->create(['configurator_id' => $configurator->id]);
    $name = View::factory()->textInput()->childOf($group)->create();
    $diet = View::factory()->textInput()->childOf($group)->create();
    Rule::factory()->validation("answer('{$name->id}') != null")->create(['view_id' => $name->id]);
    Rule::factory()->validation("answer('{$diet->id}') != null")->create(['view_id' => $diet->id]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);

    $evaluation = app(CommitConfigurationAnswers::class)($configuration, [
        new PendingAnswer($group->id, true),
        new PendingAnswer($name->id, 'Ada'),
        new PendingAnswer($diet->id, null),
    ]);

    expect(remainingValidationRules($evaluation, $diet->id))->not->toBeEmpty();
    expect(Answer::count())->toBe(0);
});

it('re-validates on commit even without a prior fetch', function () {
    $configurator = Configurator::factory()->create();
    $group = View::factory()->group()->create(['configurator_id' => $configurator->id]);
    $name = View::factory()->textInput()->childOf($group)->create();
    Rule::factory()->validation("answer('{$name->id}') != null")->create(['view_id' => $name->id]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);

    // Validation only ever runs on submitted pending answers (and their ancestors),
    // so a real caller always submits every currently-relevant field's value
    // alongside a step's acknowledgment - here, name is submitted as still empty.
    $evaluation = app(CommitConfigurationAnswers::class)($configuration, [
        new PendingAnswer($group->id, true),
        new PendingAnswer($name->id, null),
    ]);

    expect(remainingValidationRules($evaluation, $name->id))->not->toBeEmpty();
    expect(Answer::count())->toBe(0);
});
