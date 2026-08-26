<?php

use App\Enums\ViewType;
use App\Livewire\PlayConfigurator;
use App\Models\Configuration;
use App\Models\Configurator;
use App\Models\View;
use Livewire\Livewire;

beforeEach(function () {
    $this->configurator = Configurator::factory()->create();
    $this->root = View::factory()->group()->create(['configurator_id' => $this->configurator->id]);
    $this->group = View::factory()->childOf($this->root)->create([
        'type' => ViewType::Group,
        'config' => ['label' => 'Toppings', 'widget' => 'multiselect'],
        'order' => 0,
    ]);
    $this->cheese = View::factory()->checkbox('Cheese')->childOf($this->group)->create(['order' => 0]);
    $this->olives = View::factory()->checkbox('Olives')->childOf($this->group)->create(['order' => 1]);
    $this->ham = View::factory()->checkbox('Ham')->childOf($this->group)->create(['order' => 2]);
});

it('renders a multiselect group as one select element instead of independent children fields', function () {
    $component = Livewire::test(PlayConfigurator::class, ['configurator' => $this->configurator]);

    $component->assertSeeHtml('<select');
    $component->assertSeeHtml("<option value=\"{$this->cheese->id}\" >Cheese</option>");
    $component->assertSeeHtml("<option value=\"{$this->olives->id}\" >Olives</option>");
    $component->assertDontSeeHtml('type="checkbox"');
});

it('maps a multiselect selection back into each child\'s own scalar answer', function () {
    $component = Livewire::test(PlayConfigurator::class, ['configurator' => $this->configurator]);

    $component->set("groupSelections.{$this->group->id}", [$this->cheese->id, $this->ham->id]);

    expect($component->get('values')[$this->cheese->id])->toBeTrue();
    expect($component->get('values')[$this->olives->id])->toBeFalse();
    expect($component->get('values')[$this->ham->id])->toBeTrue();
});

it('does not attach any answer directly to the group itself', function () {
    $component = Livewire::test(PlayConfigurator::class, ['configurator' => $this->configurator]);

    $component->set("groupSelections.{$this->group->id}", [$this->cheese->id]);

    expect($component->get('values'))->not->toHaveKey($this->group->id);
});

it('restores previously persisted child answers into the multiselect selection on load', function () {
    $configuration = Configuration::factory()->create(['configurator_id' => $this->configurator->id]);
    answer($configuration, $this->cheese, true);
    answer($configuration, $this->olives, false);
    session(["metaconfigurator.configuration.{$this->configurator->id}" => $configuration->id]);

    $component = Livewire::test(PlayConfigurator::class, ['configurator' => $this->configurator]);

    expect($component->get('groupSelections')[$this->group->id])->toBe([$this->cheese->id]);
});

it('tolerates Livewire delivering a bare scalar instead of an array for a selection change', function () {
    $component = Livewire::test(PlayConfigurator::class, ['configurator' => $this->configurator]);

    $component->set("groupSelections.{$this->group->id}", [$this->cheese->id]);
    $component->set("groupSelections.{$this->group->id}", $this->olives->id);

    expect($component->get('values')[$this->cheese->id])->toBeFalse();
    expect($component->get('values')[$this->olives->id])->toBeTrue();
});

it('reconstructs the full selection when Livewire delivers a granular per-index update', function () {
    $component = Livewire::test(PlayConfigurator::class, ['configurator' => $this->configurator]);

    $component->set("groupSelections.{$this->group->id}", [$this->cheese->id, $this->ham->id]);
    $component->set("groupSelections.{$this->group->id}.0", $this->olives->id);

    expect($component->get('values')[$this->cheese->id])->toBeFalse();
    expect($component->get('values')[$this->olives->id])->toBeTrue();
    expect($component->get('values')[$this->ham->id])->toBeTrue();
});

it('leaves a plain group without the multiselect widget rendering children independently', function () {
    $plainGroup = View::factory()->group()->childOf($this->root)->create(['order' => 1]);
    View::factory()->checkbox('Gluten free?')->childOf($plainGroup)->create(['order' => 0]);

    $component = Livewire::test(PlayConfigurator::class, ['configurator' => $this->configurator]);

    $component->assertSeeHtml('type="checkbox"');
});

it('lays a plain group out as a vertical flex column by default', function () {
    $plainGroup = View::factory()->group()->childOf($this->root)->create(['order' => 1]);
    View::factory()->checkbox('Gluten free?')->childOf($plainGroup)->create(['order' => 0]);

    $component = Livewire::test(PlayConfigurator::class, ['configurator' => $this->configurator]);

    $component->assertSeeHtml('class="flex flex-col space-y-6"');
});

it('lays a group with direction horizontal out as a wrapping flex row', function () {
    $horizontalGroup = View::factory()->group(direction: 'horizontal')->childOf($this->root)->create(['order' => 1]);
    View::factory()->checkbox('Gluten free?')->childOf($horizontalGroup)->create(['order' => 0]);

    $component = Livewire::test(PlayConfigurator::class, ['configurator' => $this->configurator]);

    $component->assertSeeHtml('class="flex flex-row flex-wrap gap-4"');
});
