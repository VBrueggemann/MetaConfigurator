<?php

use App\Actions\DTO\ConfigurationEvaluation;
use App\Actions\Pipeline\BakeConfiguratorGraphs;
use App\Models\Configuration;
use App\Models\Configurator;
use App\Models\View;
use Illuminate\Support\Facades\DB;

function bakeGraphs(Configuration $configuration): ConfigurationEvaluation
{
    $evaluation = new ConfigurationEvaluation($configuration, []);
    $configuration->loadMissing(['configurator.rootView', 'configurator.calculations']);

    app(BakeConfiguratorGraphs::class)($evaluation, fn (ConfigurationEvaluation $evaluation) => $evaluation);

    return $evaluation;
}

it('bakes graphs_acyclic to true on a configurator with no cycles', function () {
    $configurator = Configurator::factory()->create();
    View::factory()->group()->create(['configurator_id' => $configurator->id]);
    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);

    expect($configurator->graphs_acyclic)->toBeFalse();

    bakeGraphs($configuration);

    expect($configurator->refresh()->graphs_acyclic)->toBeTrue();
});

it('bakes the calculation topological order alongside acyclicity', function () {
    $configurator = Configurator::factory()->create();
    View::factory()->group()->create(['configurator_id' => $configurator->id]);

    seedCalculations($configurator, [
        'total' => 'calculation.subtotal * 1.1',
        'subtotal' => '10',
    ]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);

    bakeGraphs($configuration);

    expect($configurator->refresh()->calculation_order)->toBe(['subtotal', 'total']);
});

it('does not re-bake once graphs_acyclic is already true', function () {
    $configurator = Configurator::factory()->create(['graphs_acyclic' => true]);
    View::factory()->group()->create(['configurator_id' => $configurator->id]);
    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);

    $evaluation = new ConfigurationEvaluation($configuration, []);
    $configuration->loadMissing(['configurator.rootView', 'configurator.calculations']);

    DB::enableQueryLog();
    app(BakeConfiguratorGraphs::class)($evaluation, fn (ConfigurationEvaluation $evaluation) => $evaluation);
    $queries = collect(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queries)->toBeEmpty();
});

it('throws a clear error on a circular calculation dependency', function () {
    $configurator = Configurator::factory()->create();
    View::factory()->group()->create(['configurator_id' => $configurator->id]);

    seedCalculations($configurator, [
        'a' => 'calculation.b + 1',
        'b' => 'calculation.a + 1',
    ]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);

    expect(fn () => bakeGraphs($configuration))
        ->toThrow(RuntimeException::class, "Circular calculation dependency involving 'a'.");

    expect($configurator->refresh()->graphs_acyclic)->toBeFalse();
});

it('throws a clear error on a circular label reference', function () {
    $configurator = Configurator::factory()->create();
    $root = View::factory()->group()->create(['configurator_id' => $configurator->id]);
    $a = View::factory()->group()->childOf($root)->create();
    $b = View::factory()->group()->childOf($root)->create();

    template($a, 'label', "A -> {{ label('{$b->id}') }}");
    template($b, 'label', "B -> {{ label('{$a->id}') }}");

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);

    expect(fn () => bakeGraphs($configuration))
        ->toThrow(RuntimeException::class, "Circular label reference involving view '{$a->id}'.");
});
