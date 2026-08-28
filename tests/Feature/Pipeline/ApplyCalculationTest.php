<?php

use App\Actions\DTO\PendingAnswer;
use App\Actions\FetchConfigurationState;
use App\Models\Configuration;
use App\Models\Configurator;
use App\Models\Rule;
use App\Models\View;

it('exposes named formula results to expressions via dot access', function () {
    $configurator = Configurator::factory()->create();
    $group = View::factory()->group()->create(['configurator_id' => $configurator->id]);
    $qty = View::factory()->textInput()->childOf($group)->create();
    $price = View::factory()->textInput()->childOf($group)->create();

    seedCalculations($configurator, [
        'subtotal' => "answer('{$qty->id}') * answer('{$price->id}')",
    ]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);

    $evaluation = app(FetchConfigurationState::class)($configuration, [new PendingAnswer($qty->id, 2), new PendingAnswer($price->id, 10)]);

    expect($evaluation->context['calculation']->subtotal)->toBe(20);
});

it('resolves formulas that reference each other regardless of declaration order', function () {
    $configurator = Configurator::factory()->create();
    View::factory()->group()->create(['configurator_id' => $configurator->id]);

    seedCalculations($configurator, [
        'total' => 'calculation.subtotal * 1.1',
        'subtotal' => '10',
    ]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);

    $evaluation = app(FetchConfigurationState::class)($configuration);

    expect($evaluation->context['calculation']->total)->toBe(11.0);
});

it('lets a visibility rule reference a calculation result', function () {
    $configurator = Configurator::factory()->create();
    $group = View::factory()->group()->create(['configurator_id' => $configurator->id]);
    $qty = View::factory()->textInput()->childOf($group)->create();
    $discountNotice = View::factory()->html('bulk discount applied')->childOf($group)->create();

    seedCalculations($configurator, [
        'qty' => "answer('{$qty->id}')",
    ]);

    Rule::factory()->visibility('calculation.qty > 10')->create(['view_id' => $discountNotice->id]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);

    $hidden = app(FetchConfigurationState::class)($configuration, [new PendingAnswer($qty->id, 5)]);
    expect(scopeIds($hidden))->not->toContain($discountNotice->id);

    $shown = app(FetchConfigurationState::class)($configuration, [new PendingAnswer($qty->id, 20)]);
    expect(scopeIds($shown))->toContain($discountNotice->id);
});

it('throws a clear error on a circular formula dependency', function () {
    $configurator = Configurator::factory()->create();
    View::factory()->group()->create(['configurator_id' => $configurator->id]);

    seedCalculations($configurator, [
        'a' => 'calculation.b + 1',
        'b' => 'calculation.a + 1',
    ]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);

    expect(fn () => app(FetchConfigurationState::class)($configuration))
        ->toThrow(RuntimeException::class, "Circular calculation dependency involving 'a'.");
});

it('falls back to 0 instead of crashing when a formula fails to evaluate', function () {
    $configurator = Configurator::factory()->create();
    $group = View::factory()->group()->create(['configurator_id' => $configurator->id]);
    $quantity = View::factory()->number()->childOf($group)->create();

    seedCalculations($configurator, [
        'subtotal' => "answer('{$quantity->id}') * 2",
    ]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);

    $evaluation = app(FetchConfigurationState::class)($configuration, [new PendingAnswer($quantity->id, '1--')]);

    expect($evaluation->context['calculation']->subtotal)->toBe(0);
});

it('lets a downstream formula see 0 when the formula it references failed to evaluate', function () {
    $configurator = Configurator::factory()->create();
    $group = View::factory()->group()->create(['configurator_id' => $configurator->id]);
    $quantity = View::factory()->number()->childOf($group)->create();

    seedCalculations($configurator, [
        'subtotal' => "answer('{$quantity->id}') * 2",
        'total' => 'calculation.subtotal + 5',
    ]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);

    $evaluation = app(FetchConfigurationState::class)($configuration, [new PendingAnswer($quantity->id, '1--')]);

    expect($evaluation->context['calculation']->total)->toBe(5);
});
