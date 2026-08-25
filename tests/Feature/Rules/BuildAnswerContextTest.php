<?php

use App\Actions\DTO\ConfigurationEvaluation;
use App\Actions\DTO\PendingAnswer;
use App\Actions\Rules\BuildAnswerContext;
use App\Actions\Rules\CommitConfigurationAnswers;
use App\Actions\Rules\FetchConfigurationState;
use App\Models\Answer;
use App\Models\Component;
use App\Models\Configuration;
use App\Models\Configurator;
use App\Models\Rule;
use App\Models\View;

/**
 * @param  array<string, mixed>  $pendingAnswers
 */
function buildAnswerContext(Configuration $configuration, array $pendingAnswers = []): ConfigurationEvaluation
{
    $evaluation = new ConfigurationEvaluation($configuration, array_map(
        fn (string $viewId, bool|int|string|null $value): PendingAnswer => new PendingAnswer($viewId, $value),
        array_keys($pendingAnswers),
        array_values($pendingAnswers),
    ));

    app(BuildAnswerContext::class)($evaluation, fn (ConfigurationEvaluation $evaluation): ConfigurationEvaluation => $evaluation);

    return $evaluation;
}

it('builds an answers map keyed by view id from the configuration\'s current answers', function () {
    $configurator = Configurator::factory()->create();
    $name = View::factory()->textInput()->create(['configurator_id' => $configurator->id]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);
    Answer::factory()->create([
        'configuration_id' => $configuration->id,
        'view_id' => $name->id,
        'value' => 'Ada',
    ]);

    $evaluation = buildAnswerContext($configuration);

    expect($evaluation->context['answers'])->toBe([$name->id => 'Ada']);
});

it('returns an empty answers map when nothing has been answered yet', function () {
    $configuration = Configuration::factory()->create();

    $evaluation = buildAnswerContext($configuration);

    expect($evaluation->context['answers'])->toBe([]);
});

it('lets a pending answer preview over a persisted one', function () {
    $configurator = Configurator::factory()->create();
    $name = View::factory()->textInput()->create(['configurator_id' => $configurator->id]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);
    Answer::factory()->create(['configuration_id' => $configuration->id, 'view_id' => $name->id, 'value' => 'Ada']);

    $evaluation = buildAnswerContext($configuration, [$name->id => 'Grace']);

    expect($evaluation->context['answers'][$name->id])->toBe('Grace');
});

it('previews a pending answer for a view with nothing persisted yet', function () {
    $configurator = Configurator::factory()->create();
    $name = View::factory()->textInput()->create(['configurator_id' => $configurator->id]);
    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);

    $evaluation = buildAnswerContext($configuration, [$name->id => 'Grace']);

    expect($evaluation->context['answers'][$name->id])->toBe('Grace');
});

it('reveals the entire tree when depth is 0', function () {
    $configurator = Configurator::factory()->create();
    $root = View::factory()->group()->create(['configurator_id' => $configurator->id]);
    $section = View::factory()->group()->childOf($root)->create(['order' => 0]);
    $subsection = View::factory()->group()->childOf($section)->create(['order' => 0]);
    $question = View::factory()->textInput()->childOf($subsection)->create(['order' => 0]);
    Component::factory()->scope(0)->create(['configurator_id' => $configurator->id]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);

    $evaluation = app(FetchConfigurationState::class)($configuration);

    expect(scopeIds($evaluation))->toContain($section->id, $subsection->id, $question->id);
});

it('lists already-answered steps as breadcrumbs, in the order they were answered', function () {
    $configurator = Configurator::factory()->create();
    $root = View::factory()->group()->create(['configurator_id' => $configurator->id]);
    $groupOne = View::factory()->group()->childOf($root)->create(['order' => 0]);
    $groupTwo = View::factory()->group()->childOf($root)->create(['order' => 1]);
    $groupThree = View::factory()->group()->childOf($root)->create(['order' => 2]);
    Component::factory()->scope(1)->create(['configurator_id' => $configurator->id]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);
    app(CommitConfigurationAnswers::class)($configuration, []);
    app(CommitConfigurationAnswers::class)($configuration, []);

    $evaluation = app(FetchConfigurationState::class)($configuration);

    expect($evaluation->componentContexts['breadcrumbs']->pluck('id')->all())
        ->toBe([$groupOne->id, $groupTwo->id]);
    expect($evaluation->componentContexts['breadcrumbs']->pluck('id'))
        ->not->toContain($groupThree->id, $root->id);
});

it('does not skip a step hidden by a visibility rule, since only Flow rules affect the next view', function () {
    $configurator = Configurator::factory()->create();
    $root = View::factory()->group()->create(['configurator_id' => $configurator->id]);
    $groupOne = View::factory()->group()->childOf($root)->create(['order' => 0]);
    $diet = View::factory()->textInput()->childOf($groupOne)->create();
    $groupTwo = View::factory()->group()->childOf($root)->create(['order' => 1]);
    Rule::factory()->visibility("answers['{$diet->id}'] != 'vegan'")->create(['view_id' => $groupTwo->id]);
    Component::factory()->scope(1)->create(['configurator_id' => $configurator->id]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);
    answer($configuration, $diet, 'vegan');
    answer($configuration, $groupOne, true);

    $evaluation = app(FetchConfigurationState::class)($configuration);

    expect(scopeIds($evaluation))->toEqualCanonicalizing([$root->id, $groupTwo->id]);
});

it('advances once a step has any answer, even if a descendant still has a validation error', function () {
    $configurator = Configurator::factory()->create();
    $root = View::factory()->group()->create(['configurator_id' => $configurator->id]);
    $groupOne = View::factory()->group()->childOf($root)->create(['order' => 0]);
    $name = View::factory()->textInput()->childOf($groupOne)->create();
    $groupTwo = View::factory()->group()->childOf($root)->create(['order' => 1]);
    Rule::factory()->validation("answers['{$name->id}'] != null")->create(['view_id' => $name->id]);
    Component::factory()->scope(1)->create(['configurator_id' => $configurator->id]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);
    answer($configuration, $groupOne, true);

    $evaluation = app(FetchConfigurationState::class)($configuration);

    expect(scopeIds($evaluation))->toEqualCanonicalizing([$root->id, $groupTwo->id]);
});
