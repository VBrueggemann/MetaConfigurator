<?php

use App\Livewire\PlayConfigurator;
use App\Models\Answer;
use App\Models\Configurator;
use App\Models\View;
use Database\Seeders\WidgetShowcaseSeeder;
use Livewire\Livewire;

beforeEach(function () {
    app(WidgetShowcaseSeeder::class)->run();

    $configurator = Configurator::where('name', 'Widget Showcase')->firstOrFail();
    $this->configurator = $configurator;

    $root = $configurator->rootView;
    $shell = View::where('parent_id', $root->id)->firstOrFail();
    $allQuestions = View::where('parent_id', $shell->id)->firstOrFail();

    $steps = View::where('parent_id', $allQuestions->id)->orderBy('order')->get();
    $this->step1 = $steps[0];
    $this->step2 = $steps[1];
    $this->step3 = $steps[2];

    $step1Fields = View::where('parent_id', $this->step1->id)->orderBy('order')->get();
    $this->maxChars = $step1Fields[0];
    $this->limitedText = $step1Fields[1];

    $traits = View::where('parent_id', $this->step2->id)->orderBy('order')->first();
    $this->courage = View::where('parent_id', $traits->id)->orderBy('order')->first();
});

it('renders every native input widget on the final questions step', function () {
    $component = Livewire::test(PlayConfigurator::class, ['configurator' => $this->configurator])
        ->set("values.{$this->maxChars->id}", 2)
        ->set("values.{$this->limitedText->id}", 'ab')
        ->call('next')
        ->set("values.{$this->courage->id}", true)
        ->call('next');

    $component->assertSeeHtml('type="checkbox"');
    $component->assertSeeHtml('type="color"');
    $component->assertSeeHtml('type="date"');
    $component->assertSeeHtml('<select');
    $component->assertSeeHtml('type="email"');
    $component->assertSeeHtml('type="number"');
    $component->assertSeeHtml('type="radio"');
    $component->assertSeeHtml('type="range"');
    $component->assertSeeHtml('type="text"');
    $component->assertSeeHtml('<datalist');
    $component->assertSee('Multi-select (dropdown)');
    $component->assertSee('Multi-select (checkboxes)');

    expect(Answer::whereIn('view_id', [$this->step1->id, $this->step2->id])->exists())->toBeFalse();
});

it('goes back one step at a time, not all the way to the first step', function () {
    $component = Livewire::test(PlayConfigurator::class, ['configurator' => $this->configurator])
        ->set("values.{$this->maxChars->id}", 2)
        ->set("values.{$this->limitedText->id}", 'ab')
        ->call('next')
        ->set("values.{$this->courage->id}", true)
        ->call('next');

    expect($component->get('scopeViewIds'))
        ->toContain($this->step3->id)
        ->not->toContain($this->step2->id);

    $component->call('back');

    expect($component->get('scopeViewIds'))
        ->toContain($this->step2->id)
        ->not->toContain($this->step1->id)
        ->not->toContain($this->step3->id);
});
