<?php

use App\Enums\ViewType;
use App\Livewire\PlayConfigurator;
use App\Models\Configurator;
use App\Models\View;
use Livewire\Livewire;

beforeEach(function () {
    $this->configurator = Configurator::factory()->create();
    $this->root = View::factory()->group()->create(['configurator_id' => $this->configurator->id]);
});

it('renders a select widget with its configured options', function () {
    $options = [['value' => 'a', 'label' => 'Option A'], ['value' => 'b', 'label' => 'Option B']];
    View::factory()->select($options, 'Pick one')->childOf($this->root)->create(['order' => 0]);

    $component = Livewire::test(PlayConfigurator::class, ['configurator' => $this->configurator]);

    $component->assertSee('Pick one');
    $component->assertSeeHtml('<option value="a">Option A</option>');
    $component->assertSeeHtml('<option value="b">Option B</option>');
});

it('renders a checkbox widget as a checkbox input', function () {
    View::factory()->checkbox('Subscribe?')->childOf($this->root)->create(['order' => 0]);

    $component = Livewire::test(PlayConfigurator::class, ['configurator' => $this->configurator]);

    $component->assertSeeHtml('type="checkbox"');
});

it('seeds a field default from config rather than from its widget name', function () {
    $checkbox = View::factory()->childOf($this->root)->create([
        'type' => ViewType::Input,
        'config' => ['label' => 'Newsletter', 'widget' => 'checkbox', 'default' => true],
        'order' => 0,
    ]);

    $component = Livewire::test(PlayConfigurator::class, ['configurator' => $this->configurator]);

    expect($component->get('values')[$checkbox->id])->toBeTrue();
});

it('defaults a field with no configured default to null', function () {
    $text = View::factory()->textInput('Anything else?')->childOf($this->root)->create(['order' => 0]);

    $component = Livewire::test(PlayConfigurator::class, ['configurator' => $this->configurator]);

    expect($component->get('values')[$text->id])->toBeNull();
});
