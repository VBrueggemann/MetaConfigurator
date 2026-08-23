<?php

use App\Actions\Rules\EvaluateRule;
use App\Enums\RuleComponent;
use App\Models\Rule;

it('evaluates true when the rule\'s expression holds', function () {
    $rule = Rule::factory()->make(['expression' => 'age >= 18']);

    $evaluateRule = app(EvaluateRule::class);

    expect($evaluateRule($rule, ['age' => 21]))->toBeTrue();
    expect($evaluateRule($rule, ['age' => 12]))->toBeFalse();
});

it('works for each rule component', function (RuleComponent $component) {
    $rule = Rule::factory()->make(['component' => $component, 'expression' => 'true']);

    expect(app(EvaluateRule::class)($rule, []))->toBeTrue();
})->with([RuleComponent::Visibility, RuleComponent::Validation]);

it('returns false rather than throwing when the expression references a missing field', function () {
    $rule = Rule::factory()->make(['expression' => "answers['missing'] == 'x'"]);

    expect(app(EvaluateRule::class)($rule, ['answers' => []]))->toBeFalse();
});
