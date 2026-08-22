<?php

use App\Actions\Rules\EvaluateExpression;
use App\Models\Answer;
use App\Models\Configuration;
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

it('supports compound logic with && and ||', function () {
    $evaluate = app(EvaluateExpression::class);

    expect($evaluate('a && b', ['a' => true, 'b' => true]))->toBeTrue();
    expect($evaluate('a && b', ['a' => true, 'b' => false]))->toBeFalse();
    expect($evaluate('a || b', ['a' => false, 'b' => true]))->toBeTrue();
});

it('evaluates a rule expression against a Configuration\'s current answers', function () {
    $configuration = Configuration::factory()->create();
    $diet = View::factory()->select([['value' => 'vegetarian', 'label' => 'Vegetarian'], ['value' => 'omnivore', 'label' => 'Omnivore']])->create([
        'configurator_id' => $configuration->configurator_id,
    ]);
    Answer::factory()->create([
        'configuration_id' => $configuration->id,
        'view_id' => $diet->id,
        'value' => 'vegetarian',
    ]);
    $evaluate = app(EvaluateExpression::class);
    $context = ['answers' => $configuration->answers()->get()->pluck('value', 'view_id')->all()];

    expect($evaluate("answers['{$diet->id}'] == 'vegetarian'", $context))->toBeTrue();
    expect($evaluate("answers['{$diet->id}'] == 'omnivore'", $context))->toBeFalse();
});
