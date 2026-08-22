<?php

use App\Livewire\PlayConfigurator;
use App\Models\Answer;
use App\Models\Configuration;
use App\Models\Configurator;
use App\Models\View;
use Database\Seeders\FictionalSurveySeeder;
use Livewire\Livewire;

beforeEach(function () {
    app(FictionalSurveySeeder::class)->run();

    $this->configurator = Configurator::where('name', 'Dinner Party Survey')->firstOrFail();

    $groups = View::where('configurator_id', $this->configurator->id)->whereNull('parent_id')->orderBy('order')->get();
    $this->aboutYou = $groups[0];
    $this->general = $groups[1];
    $this->veganExtras = $groups[2];
    $this->thankYou = $groups[3];

    $items = View::where('configurator_id', $this->configurator->id)->where('parent_id', $this->aboutYou->id)->orderBy('order')->get();
    $this->name = $items[0];
    $this->diet = $items[1];
    $this->activities = $items[2];
    $this->allergy = $items[3];
});

it('starts a new Configuration on first visit and remembers it across a fresh visit', function () {
    expect(Configuration::count())->toBe(0);

    Livewire::test(PlayConfigurator::class, ['configurator' => $this->configurator]);

    expect(Configuration::count())->toBe(1);

    Livewire::test(PlayConfigurator::class, ['configurator' => $this->configurator]);

    expect(Configuration::count())->toBe(1);
});

it('persists an answer immediately when a field changes', function () {
    $component = Livewire::test(PlayConfigurator::class, ['configurator' => $this->configurator]);

    $component->set("values.{$this->name->id}", 'Ada');

    $configuration = Configuration::sole();
    expect(Answer::where('configuration_id', $configuration->id)->where('view_id', $this->name->id)->first()->value)->toBe('Ada');
});

it('reveals the allergy question once diet is set to vegan', function () {
    $component = Livewire::test(PlayConfigurator::class, ['configurator' => $this->configurator]);

    expect($component->get('visibleViewIds'))->not->toContain($this->allergy->id);

    $component->set("values.{$this->diet->id}", 'vegan');

    expect($component->get('visibleViewIds'))->toContain($this->allergy->id);
});

it('does not advance past About You while required fields are missing', function () {
    $component = Livewire::test(PlayConfigurator::class, ['configurator' => $this->configurator]);

    $component->call('next');

    expect($component->get('currentGroup')->id)->toBe($this->aboutYou->id);
});

it('walks a non-vegan respondent through General to Thank You', function () {
    $component = Livewire::test(PlayConfigurator::class, ['configurator' => $this->configurator])
        ->set("values.{$this->name->id}", 'Ada')
        ->set("values.{$this->diet->id}", 'omnivore')
        ->set("values.{$this->activities->id}", ['hiking'])
        ->call('next');

    expect($component->get('currentGroup')->id)->toBe($this->general->id);

    // A fresh mount (simulating a page reload) must stay on General — not
    // skip past it — until its content has actually been acknowledged.
    $reloaded = Livewire::test(PlayConfigurator::class, ['configurator' => $this->configurator]);
    expect($reloaded->get('currentGroup')->id)->toBe($this->general->id);

    $component->call('next');

    expect($component->get('currentGroup')->id)->toBe($this->thankYou->id);
});

it('walks a vegan respondent through Vegan Extras to Thank You', function () {
    $component = Livewire::test(PlayConfigurator::class, ['configurator' => $this->configurator])
        ->set("values.{$this->name->id}", 'Ada')
        ->set("values.{$this->diet->id}", 'vegan')
        ->set("values.{$this->activities->id}", ['cooking'])
        ->set("values.{$this->allergy->id}", 'peanuts')
        ->call('next');

    expect($component->get('currentGroup')->id)->toBe($this->veganExtras->id);

    $component->call('next');

    expect($component->get('currentGroup')->id)->toBe($this->thankYou->id);
});
