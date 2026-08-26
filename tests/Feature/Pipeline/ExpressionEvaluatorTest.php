<?php

use App\Actions\DTO\PendingAnswer;
use App\Actions\Pipeline\EvaluateExpression;
use App\Models\Answer;
use App\Models\AnswerChunk;
use App\Models\Configuration;
use App\Models\Rule;
use App\Models\View;

it('evaluates a literal boolean expression', function () {
    $evaluate = app(EvaluateExpression::class);

    expect($evaluate('true'))->toBeTrue();
    expect($evaluate('1 == 2'))->toBeFalse();
});

it('evaluates an expression against a given context', function () {
    $evaluate = app(EvaluateExpression::class);

    expect($evaluate('age >= 18', ['age' => 21]))->toBeTrue();
    expect($evaluate('age >= 18', ['age' => 12]))->toBeFalse();
});

it('supports strlen() and count() as custom functions', function () {
    $evaluate = app(EvaluateExpression::class);

    expect($evaluate('strlen(value) >= 3', ['value' => 'hi']))->toBeFalse();
    expect($evaluate('strlen(value) >= 3', ['value' => 'hello']))->toBeTrue();
    expect($evaluate('count(value) <= 2', ['value' => ['a', 'b']]))->toBeTrue();
    expect($evaluate('count(value) <= 2', ['value' => ['a', 'b', 'c']]))->toBeFalse();
});

it('supports compound logic with && and ||', function () {
    $evaluate = app(EvaluateExpression::class);

    expect($evaluate('a && b', ['a' => true, 'b' => true]))->toBeTrue();
    expect($evaluate('a && b', ['a' => true, 'b' => false]))->toBeFalse();
    expect($evaluate('a || b', ['a' => false, 'b' => true]))->toBeTrue();
});

it('evaluates a rule expression against a Configuration\'s current answers via answer()', function () {
    $configuration = Configuration::factory()->create();
    $diet = View::factory()->select([['value' => 'vegetarian', 'label' => 'Vegetarian'], ['value' => 'omnivore', 'label' => 'Omnivore']])->create([
        'configurator_id' => $configuration->configurator_id,
    ]);
    $diet->setRelation('descendants', collect());
    Answer::factory()->create([
        'configuration_id' => $configuration->id,
        'view_id' => $diet->id,
        'value' => 'vegetarian',
    ]);
    $evaluate = app(EvaluateExpression::class);
    $context = [
        'modelsById' => [View::class => [$diet->id => $diet]],
        'relevantAnswers' => $configuration->relevantAnswers()->get(),
    ];

    expect($evaluate("answer('{$diet->id}') == 'vegetarian'", $context))->toBeTrue();
    expect($evaluate("answer('{$diet->id}') == 'omnivore'", $context))->toBeFalse();
});

it('answer() previews a pending answer over a persisted one, unless __persistedOnly is set', function () {
    $configuration = Configuration::factory()->create();
    $diet = View::factory()->select([['value' => 'vegetarian', 'label' => 'Vegetarian'], ['value' => 'omnivore', 'label' => 'Omnivore']])->create([
        'configurator_id' => $configuration->configurator_id,
    ]);
    $diet->setRelation('descendants', collect());
    Answer::factory()->create(['configuration_id' => $configuration->id, 'view_id' => $diet->id, 'value' => 'vegetarian']);
    $evaluate = app(EvaluateExpression::class);
    $context = [
        'modelsById' => [View::class => [$diet->id => $diet]],
        'relevantAnswers' => $configuration->relevantAnswers()->get(),
        'pendingAnswers' => [new PendingAnswer($diet->id, 'omnivore')],
    ];

    expect($evaluate("answer('{$diet->id}')", $context))->toBe('omnivore');
    expect($evaluate("answer('{$diet->id}')", [...$context, '__persistedOnly' => true]))->toBe('vegetarian');
});

it('answer() aggregates descendant answers for a group with no direct answer of its own', function () {
    $configuration = Configuration::factory()->create();
    $group = View::factory()->group()->create(['configurator_id' => $configuration->configurator_id]);
    $childOne = View::factory()->textInput()->childOf($group)->create(['order' => 0]);
    $childTwo = View::factory()->textInput()->childOf($group)->create(['order' => 1]);
    $group->setRelation('descendants', collect([$childOne, $childTwo]));
    Answer::factory()->create(['configuration_id' => $configuration->id, 'view_id' => $childOne->id, 'value' => 'red']);
    Answer::factory()->create(['configuration_id' => $configuration->id, 'view_id' => $childTwo->id, 'value' => 'blue']);
    $evaluate = app(EvaluateExpression::class);

    expect($evaluate("answer('{$group->id}')", [
        'modelsById' => [View::class => [$group->id => $group]],
        'relevantAnswers' => $configuration->relevantAnswers()->get(),
    ]))->toBe('red, blue');
});

it('answer() synthesizes true for a view that anchors a relevant chunk with no Answer row anywhere in its subtree', function () {
    $configuration = Configuration::factory()->create();
    $group = View::factory()->group()->create(['configurator_id' => $configuration->configurator_id]);
    $group->setRelation('descendants', collect());
    $chunk = AnswerChunk::factory()->create(['configuration_id' => $configuration->id, 'anchor_view_id' => $group->id]);
    $evaluate = app(EvaluateExpression::class);

    expect($evaluate("answer('{$group->id}') == true", [
        'modelsById' => [View::class => [$group->id => $group]],
        'relevantAnswerChunks' => collect([$chunk]),
    ]))->toBeTrue();
});

it('throws a RuntimeException from answer() when the referenced view is not in modelsById', function () {
    $evaluate = app(EvaluateExpression::class);

    $evaluate("answer('missing-id')", ['modelsById' => [View::class => []]]);
})->throws(RuntimeException::class);

it('joins the messages of failing validation rules already loaded on the view via validationError()', function () {
    $field = View::factory()->textInput()->create();
    $tooShort = Rule::factory()->validation('false')->create(['view_id' => $field->id, 'meta' => ['message' => 'Too short']]);
    $tooLong = Rule::factory()->validation('false')->create(['view_id' => $field->id, 'meta' => ['message' => 'Too long']]);
    $field->setRelation('rules', collect([$tooShort, $tooLong]));
    $evaluate = app(EvaluateExpression::class);

    expect($evaluate("validationError('{$field->id}')", [
        'modelsById' => [View::class => [$field->id => $field]],
    ]))->toBe('Too short, Too long');
});

it('returns an empty string from validationError() when no failing validation rules remain on the view', function () {
    $field = View::factory()->textInput()->create();
    $field->setRelation('rules', collect());
    $evaluate = app(EvaluateExpression::class);

    expect($evaluate("validationError('{$field->id}')", [
        'modelsById' => [View::class => [$field->id => $field]],
    ]))->toBe('');
});
