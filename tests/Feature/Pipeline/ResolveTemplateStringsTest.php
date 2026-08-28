<?php

use App\Actions\CommitConfigurationAnswers;
use App\Actions\DTO\PendingAnswer;
use App\Actions\FetchConfigurationState;
use App\Actions\Pipeline\ResolveTemplate;
use App\Models\Configuration;
use App\Models\Configurator;
use App\Models\Rule;
use App\Models\View;

it('substitutes a pending, not-yet-committed answer into a later view label', function () {
    $configurator = Configurator::factory()->create();
    $group = View::factory()->group()->create(['configurator_id' => $configurator->id]);
    $name = View::factory()->textInput()->childOf($group)->create();
    $greeting = View::factory()->html('placeholder')->childOf($group)->create();
    $greeting->update(['config' => ['content' => "Hello {{ answer('{$name->id}') }}!"]]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);

    $evaluation = app(FetchConfigurationState::class)($configuration, [new PendingAnswer($name->id, 'Ada')]);

    $resolved = $evaluation->componentContexts['scope']->first()->children->firstWhere('id', $greeting->id);

    expect($resolved->config['content'])->toBe('Hello Ada!');
});

it('substitutes a calculation result into display content', function () {
    $configurator = Configurator::factory()->create();
    $group = View::factory()->group()->create(['configurator_id' => $configurator->id]);
    $total = View::factory()->html('placeholder')->childOf($group)->create();
    $total->update(['config' => ['content' => 'Sum: {{ calculation.total }}']]);

    seedCalculations($configurator, ['total' => '10 + 5']);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);

    $evaluation = app(FetchConfigurationState::class)($configuration);

    $resolved = $evaluation->componentContexts['scope']->first()->children->firstWhere('id', $total->id);

    expect($resolved->config['content'])->toBe('Sum: 15');
});

it('falls back to the raw placeholder for an unknown or unanswered reference', function () {
    $configurator = Configurator::factory()->create();
    $group = View::factory()->group()->create(['configurator_id' => $configurator->id]);
    $unanswered = View::factory()->textInput()->childOf($group)->create();
    $display = View::factory()->html('placeholder')->childOf($group)->create();
    $display->update(['config' => [
        'content' => "Unanswered: {{ answer('{$unanswered->id}') }} | Unknown: {{ answer('00000000-0000-0000-0000-000000000000') }}",
    ]]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);

    $evaluation = app(FetchConfigurationState::class)($configuration);

    $resolved = $evaluation->componentContexts['scope']->first()->children->firstWhere('id', $display->id);

    expect($resolved->config['content'])->toBe(
        "Unanswered: {{ answer('{$unanswered->id}') }} | Unknown: {{ answer('00000000-0000-0000-0000-000000000000') }}"
    );
});

it('escapes a substituted value inside raw content but keeps authored html raw', function () {
    $configurator = Configurator::factory()->create();
    $group = View::factory()->group()->create(['configurator_id' => $configurator->id]);
    $comment = View::factory()->textInput()->childOf($group)->create();
    $display = View::factory()->html('placeholder')->childOf($group)->create();
    $display->update(['config' => ['content' => "<strong>Hi {{ answer('{$comment->id}') }}</strong>"]]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);

    $evaluation = app(FetchConfigurationState::class)($configuration, [
        new PendingAnswer($comment->id, '<script>alert(1)</script>'),
    ]);

    $resolved = $evaluation->componentContexts['scope']->first()->children->firstWhere('id', $display->id);

    expect($resolved->config['content'])->toBe('<strong>Hi &lt;script&gt;alert(1)&lt;/script&gt;</strong>');
});

it('resolves another view label and its validation error via label() and validationError()', function () {
    $configurator = Configurator::factory()->create();
    $group = View::factory()->group()->create(['configurator_id' => $configurator->id]);
    $field = View::factory()->textInput('Shoe size')->childOf($group)->create();
    Rule::factory()->validation('false')->create(['view_id' => $field->id, 'meta' => ['message' => 'bad value']]);

    $summary = View::factory()->html('placeholder')->childOf($group)->create();
    $summary->update(['config' => [
        'content' => "Field label: {{ label('{$field->id}') }} | Rule says: {{ validationError('{$field->id}') }}",
    ]]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);

    $evaluation = app(FetchConfigurationState::class)($configuration, [new PendingAnswer($field->id, 'bad')]);

    $resolvedSummary = $evaluation->componentContexts['scope']->first()->children->firstWhere('id', $summary->id);

    expect($resolvedSummary->config['content'])->toBe('Field label: Shoe size | Rule says: bad value');
});

it('resolves validationError() as empty for a passing rule that gets pruned', function () {
    $configurator = Configurator::factory()->create();
    $group = View::factory()->group()->create(['configurator_id' => $configurator->id]);
    $field = View::factory()->textInput('Shoe size')->childOf($group)->create();
    Rule::factory()->validation('true')->create(['view_id' => $field->id, 'meta' => ['message' => 'should not appear']]);

    $summary = View::factory()->html('placeholder')->childOf($group)->create();
    $summary->update(['config' => [
        'content' => "Rule says: {{ validationError('{$field->id}') }}",
    ]]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);

    $evaluation = app(FetchConfigurationState::class)($configuration, [new PendingAnswer($field->id, 'ok')]);

    $resolvedSummary = $evaluation->componentContexts['scope']->first()->children->firstWhere('id', $summary->id);

    expect($resolvedSummary->config['content'])->toBe('Rule says: ');
});

it('resolves validationError() as empty for a failing rule on a view that was never pending', function () {
    $configurator = Configurator::factory()->create();
    $group = View::factory()->group()->create(['configurator_id' => $configurator->id]);
    $field = View::factory()->textInput('Shoe size')->childOf($group)->create();
    Rule::factory()->validation('false')->create(['view_id' => $field->id, 'meta' => ['message' => 'should not appear']]);

    $summary = View::factory()->html('placeholder')->childOf($group)->create();
    $summary->update(['config' => [
        'content' => "Rule says: {{ validationError('{$field->id}') }}",
    ]]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);

    $evaluation = app(FetchConfigurationState::class)($configuration);

    $resolvedSummary = $evaluation->componentContexts['scope']->first()->children->firstWhere('id', $summary->id);

    expect($resolvedSummary->config['content'])->toBe('Rule says: ');
});

it('breaks a circular label reference instead of looping forever', function () {
    $configurator = Configurator::factory()->create();
    $root = View::factory()->group()->create(['configurator_id' => $configurator->id]);
    $a = View::factory()->group()->childOf($root)->create();
    $b = View::factory()->group()->childOf($root)->create();

    $a->update(['config' => ['label' => "A -> {{ label('{$b->id}') }}"]]);
    $b->update(['config' => ['label' => "B -> {{ label('{$a->id}') }}"]]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);

    $evaluation = app(FetchConfigurationState::class)($configuration);

    $resolvedA = $evaluation->componentContexts['scope']->first()->children->firstWhere('id', $a->id);

    expect($resolvedA->config['label'])->toContain('{{ label(');
});

it('templates the validation message with a pending answer at fetch time', function () {
    $configurator = Configurator::factory()->create();
    $group = View::factory()->group()->create(['configurator_id' => $configurator->id]);
    $field = View::factory()->number('Quantity')->childOf($group)->create();
    Rule::factory()->validation("answer('{$field->id}') >= 10")->create([
        'view_id' => $field->id,
        'meta' => ['message' => "{{ answer('{$field->id}') }} is too low"],
    ]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);

    $evaluation = app(FetchConfigurationState::class)($configuration, [new PendingAnswer($field->id, 5)]);

    $resolvedField = $evaluation->componentContexts['scope']->first()->children->firstWhere('id', $field->id);

    expect($resolvedField->rules->first()->meta['message'])->toBe('5 is too low');
});

it('templates the validation message at commit time', function () {
    $configurator = Configurator::factory()->create();
    $group = View::factory()->group()->create(['configurator_id' => $configurator->id]);
    $field = View::factory()->number('Quantity')->childOf($group)->create();
    Rule::factory()->validation("answer('{$field->id}') >= 10")->create([
        'view_id' => $field->id,
        'meta' => ['message' => "{{ answer('{$field->id}') }} is too low"],
    ]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);

    $evaluation = app(CommitConfigurationAnswers::class)($configuration, [new PendingAnswer($field->id, 5)]);

    $resolvedField = $evaluation->componentContexts['scope']->first()->children->firstWhere('id', $field->id);

    expect($resolvedField->rules->first()->meta['message'])->toBe('5 is too low');
});

it('joins an array result with a comma when substituted', function () {
    $resolved = app(ResolveTemplate::class)('Tags: {{ tags }}', ['tags' => ['red', 'green']]);

    expect($resolved)->toBe('Tags: red, green');
});
