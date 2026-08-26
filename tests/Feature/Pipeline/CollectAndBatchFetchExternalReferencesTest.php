<?php

use App\Actions\DTO\ConfigurationEvaluation;
use App\Actions\DTO\PendingAnswer;
use App\Actions\Pipeline\ApplyFlow;
use App\Actions\Pipeline\CollectAndBatchFetchExternalReferences;
use App\Models\Answer;
use App\Models\Component;
use App\Models\Configuration;
use App\Models\Configurator;
use App\Models\Rule;
use App\Models\View;
use Illuminate\Support\Facades\DB;

/**
 * @param  array<string, mixed>  $pendingAnswers
 */
function collectExternalReferences(Configuration $configuration, array $pendingAnswers = []): ConfigurationEvaluation
{
    $evaluation = new ConfigurationEvaluation($configuration, array_map(
        fn (string $viewId, mixed $value): PendingAnswer => new PendingAnswer($viewId, $value),
        array_keys($pendingAnswers),
        array_values($pendingAnswers),
    ));

    $configuration->load(['relevantAnswers', 'relevantAnswerChunks']);
    $configuration->loadMissing('configurator.rootView');

    app(ApplyFlow::class)($evaluation, fn (ConfigurationEvaluation $evaluation) => $evaluation);
    app(CollectAndBatchFetchExternalReferences::class)($evaluation, fn (ConfigurationEvaluation $evaluation) => $evaluation);

    return $evaluation;
}

it('puts every in-scope view into modelsById', function () {
    $configurator = Configurator::factory()->create();
    $root = View::factory()->group()->create(['configurator_id' => $configurator->id]);
    $name = View::factory()->textInput('Full name')->childOf($root)->create(['order' => 0]);
    Rule::factory()->visibility('true')->create(['view_id' => $name->id]);
    Component::factory()->scope(0)->create(['configurator_id' => $configurator->id]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);

    $evaluation = collectExternalReferences($configuration);

    expect($evaluation->context['modelsById'][View::class])->toHaveKeys([$root->id, $name->id]);
});

it('does not batch-fetch views when nothing external is referenced and no descendants are needed', function () {
    $configurator = Configurator::factory()->create();
    $root = View::factory()->group()->create(['configurator_id' => $configurator->id]);
    $name = View::factory()->textInput()->childOf($root)->create(['order' => 0]);
    Rule::factory()->visibility('true')->create(['view_id' => $name->id]);
    Component::factory()->scope(0)->create(['configurator_id' => $configurator->id]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);
    $evaluation = new ConfigurationEvaluation($configuration, []);
    $configuration->load(['relevantAnswers', 'relevantAnswerChunks']);
    $configuration->loadMissing('configurator.rootView');
    app(ApplyFlow::class)($evaluation, fn (ConfigurationEvaluation $evaluation) => $evaluation);

    DB::enableQueryLog();
    app(CollectAndBatchFetchExternalReferences::class)($evaluation, fn (ConfigurationEvaluation $evaluation) => $evaluation);
    $queries = collect(DB::getQueryLog())->pluck('query');
    DB::disableQueryLog();

    expect($queries->filter(fn (string $query): bool => str_contains($query, '"views"')))->toBeEmpty();
});

it('batch-fetches a view referenced by answer() outside the current scope', function () {
    $configurator = Configurator::factory()->create();
    $root = View::factory()->group()->create(['configurator_id' => $configurator->id]);
    $stepOne = View::factory()->group()->childOf($root)->create(['order' => 0]);
    $outside = View::factory()->textInput()->childOf($stepOne)->create(['order' => 0]);
    $stepTwo = View::factory()->group()->childOf($root)->create(['order' => 1]);
    $target = View::factory()->textInput()->childOf($stepTwo)->create(['order' => 0]);
    Rule::factory()->visibility("answer('{$outside->id}') == 'x'")->create(['view_id' => $target->id]);
    Component::factory()->scope(1)->create(['configurator_id' => $configurator->id]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);
    answer($configuration, $stepOne, true);
    Answer::factory()->create(['configuration_id' => $configuration->id, 'view_id' => $outside->id, 'value' => 'x']);

    $evaluation = collectExternalReferences($configuration);

    expect($evaluation->context['modelsById'][View::class][$outside->id]->id)->toBe($outside->id);
    expect(scopeIds($evaluation))->not->toContain($outside->id);
});

it('batch-fetches a view referenced by label() outside the current scope', function () {
    $configurator = Configurator::factory()->create();
    $root = View::factory()->group()->create(['configurator_id' => $configurator->id]);
    $stepOne = View::factory()->group()->childOf($root)->create(['order' => 0]);
    $outside = View::factory()->textInput('Outside label')->childOf($stepOne)->create(['order' => 0]);
    $stepTwo = View::factory()->group()->childOf($root)->create(['order' => 1]);
    $target = View::factory()->textInput()->childOf($stepTwo)->create([
        'order' => 0,
        'config' => ['label' => "{{ label('{$outside->id}') }}", 'widget' => 'text'],
    ]);
    Component::factory()->scope(1)->create(['configurator_id' => $configurator->id]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);
    answer($configuration, $stepOne, true);

    $evaluation = collectExternalReferences($configuration);

    expect($evaluation->context['modelsById'][View::class][$outside->id]->config['label'])->toBe('Outside label');
});

it('loads descendants for an in-scope group referenced by answer() without an external query', function () {
    $configurator = Configurator::factory()->create();
    $root = View::factory()->group()->create(['configurator_id' => $configurator->id]);
    $child = View::factory()->textInput()->childOf($root)->create(['order' => 0]);
    Rule::factory()->visibility("answer('{$root->id}') == 'true, x'")->create(['view_id' => $child->id]);
    Component::factory()->scope(0)->create(['configurator_id' => $configurator->id]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);

    $evaluation = collectExternalReferences($configuration);

    expect($evaluation->context['modelsById'][View::class][$root->id]->relationLoaded('descendants'))->toBeTrue();
});
