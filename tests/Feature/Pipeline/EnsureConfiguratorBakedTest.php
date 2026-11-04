<?php

use App\Actions\DTO\ConfigurationEvaluation;
use App\Actions\Pipeline\EnsureConfiguratorBaked;
use App\Models\Configuration;
use App\Models\Configurator;
use App\Models\View;
use Illuminate\Support\Facades\DB;

function ensureBaked(Configuration $configuration): ConfigurationEvaluation
{
    $evaluation = new ConfigurationEvaluation($configuration, []);
    $configuration->loadMissing(['configurator.rootView', 'configurator.calculations']);

    app(EnsureConfiguratorBaked::class)($evaluation, fn (ConfigurationEvaluation $evaluation) => $evaluation);

    return $evaluation;
}

it('marks a configurator baked when there are no cycles', function () {
    $configurator = Configurator::factory()->create();
    View::factory()->group()->create(['configurator_id' => $configurator->id]);
    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);

    expect($configurator->is_baked)->toBeFalse();

    ensureBaked($configuration);

    expect($configurator->refresh()->is_baked)->toBeTrue();
});

it('bakes the calculation topological order alongside acyclicity', function () {
    $configurator = Configurator::factory()->create();
    View::factory()->group()->create(['configurator_id' => $configurator->id]);

    seedCalculations($configurator, [
        'total' => 'calculation.subtotal * 1.1',
        'subtotal' => '10',
    ]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);

    ensureBaked($configuration);

    expect($configurator->refresh()->calculations->sortBy('order')->pluck('name')->values()->all())
        ->toBe(['subtotal', 'total']);
});

it('does not re-bake once already baked', function () {
    $configurator = Configurator::factory()->create(['is_baked' => true]);
    View::factory()->group()->create(['configurator_id' => $configurator->id]);
    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);

    $evaluation = new ConfigurationEvaluation($configuration, []);
    $configuration->loadMissing(['configurator.rootView', 'configurator.calculations']);

    DB::enableQueryLog();
    app(EnsureConfiguratorBaked::class)($evaluation, fn (ConfigurationEvaluation $evaluation) => $evaluation);
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

    expect(fn () => ensureBaked($configuration))
        ->toThrow(RuntimeException::class, "Circular dependency involving 'a' of type 'App\\Models\\Calculation'.");

    expect($configurator->refresh()->is_baked)->toBeFalse();
});

it('throws a clear error on a circular label reference', function () {
    $configurator = Configurator::factory()->create();
    $root = View::factory()->group()->create(['configurator_id' => $configurator->id]);
    $a = View::factory()->group()->childOf($root)->create();
    $b = View::factory()->group()->childOf($root)->create();

    template($a, 'label', "A -> {{ label('{$b->id}') }}");
    template($b, 'label', "B -> {{ label('{$a->id}') }}");

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);

    expect(fn () => ensureBaked($configuration))
        ->toThrow(RuntimeException::class, "Circular dependency involving '{$a->id}' of type 'App\\Models\\View'.");
});
