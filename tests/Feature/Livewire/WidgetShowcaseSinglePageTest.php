<?php

use App\Livewire\PlayConfigurator;
use App\Models\Configurator;
use App\Models\View;
use Database\Seeders\WidgetShowcaseSeeder;
use Livewire\Livewire;

beforeEach(function () {
    app()->make(WidgetShowcaseSeeder::class, ['depth' => 2])->run();

    $this->configurator = Configurator::where('name', 'Widget Showcase')->firstOrFail();

    $root = $this->configurator->rootView;
    $shell = View::where('parent_id', $root->id)->firstOrFail();
    $this->allQuestions = View::where('parent_id', $shell->id)->firstOrFail();
});

it('puts every step on a single page', function () {
    $component = Livewire::test(PlayConfigurator::class, ['configurator' => $this->configurator]);

    expect($component->get('scopeViewIds'))->toContain($this->allQuestions->id);

    $component->assertSee('Max characters allowed in the next field');
    $component->assertSee('Pick your strengths');
    $component->assertSee('Colour');
    $component->assertSee('Thanks for trying every widget!', false);
});
