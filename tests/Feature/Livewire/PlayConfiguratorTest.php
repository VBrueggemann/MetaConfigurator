<?php

use App\Enums\ViewType;
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

    $root = $this->configurator->rootView;
    $groups = View::where('parent_id', $root->id)->orderBy('order')->get();
    $this->aboutYou = $groups[0];
    $this->general = $groups[1];
    $this->veganExtras = $groups[2];
    $this->thankYou = $groups[3];

    $items = View::where('configurator_id', $this->configurator->id)->where('parent_id', $this->aboutYou->id)->orderBy('order')->get();
    $this->name = $items[0];
    $this->diet = $items[1];
    $this->activities = $items[2];
    $this->allergy = $items[3];

    $activityItems = View::where('parent_id', $this->activities->id)->where('type', ViewType::Checkbox)->orderBy('order')->get();
    $this->hiking = $activityItems[0];
    $this->cooking = $activityItems[3];
});

it('starts a new Configuration on first visit and remembers it across a fresh visit', function () {
    expect(Configuration::count())->toBe(0);

    Livewire::test(PlayConfigurator::class, ['configurator' => $this->configurator]);

    expect(Configuration::count())->toBe(1);

    Livewire::test(PlayConfigurator::class, ['configurator' => $this->configurator]);

    expect(Configuration::count())->toBe(1);
});

it('does not persist an answer until commit is called', function () {
    $component = Livewire::test(PlayConfigurator::class, ['configurator' => $this->configurator]);

    $component->set("values.{$this->name->id}", 'Ada');

    expect(Answer::count())->toBe(0);
});

it('reveals the allergy question once diet is set to vegan, without persisting', function () {
    $component = Livewire::test(PlayConfigurator::class, ['configurator' => $this->configurator]);

    expect($component->get('visibleViewIds'))->not->toContain($this->allergy->id);

    $component->set("values.{$this->diet->id}", 'vegan');

    expect($component->get('visibleViewIds'))->toContain($this->allergy->id);
    expect(Answer::count())->toBe(0);
});

it('does not advance past About You while required fields are missing', function () {
    $component = Livewire::test(PlayConfigurator::class, ['configurator' => $this->configurator]);

    $component->call('commit');

    expect($component->get('scopeViewIds'))->toContain($this->aboutYou->id);
    expect(Answer::count())->toBe(0);
});

it('walks a non-vegan respondent to Vegan Extras, folding General into the same commit', function () {
    // Setting diet live already moves scope onto General (its Flow rule matches for
    // an omnivore respondent), so by the time commit() runs it acknowledges General
    // alongside About You's fields in the same request. Vegan Extras' own Flow rule
    // doesn't match for an omnivore respondent, so the order-based default fallback
    // is what lands scope there next.
    $component = Livewire::test(PlayConfigurator::class, ['configurator' => $this->configurator])
        ->set("values.{$this->name->id}", 'Ada')
        ->set("values.{$this->diet->id}", 'omnivore')
        ->set("values.{$this->hiking->id}", true)
        ->call('commit');

    expect($component->get('scopeViewIds'))
        ->toContain($this->veganExtras->id)
        ->not->toContain($this->general->id);
});

it('walks a vegan respondent to Thank You, skipping General and folding Vegan Extras into the same commit', function () {
    // Setting diet live already moves scope onto Vegan Extras (General's Flow rule
    // never matches for a vegan respondent, and Vegan Extras' own rule does), so by
    // the time commit() runs it acknowledges Vegan Extras alongside About You's
    // fields in the same request, and scope advances straight to Thank You.
    $component = Livewire::test(PlayConfigurator::class, ['configurator' => $this->configurator])
        ->set("values.{$this->name->id}", 'Ada')
        ->set("values.{$this->diet->id}", 'vegan')
        ->set("values.{$this->cooking->id}", true)
        ->set("values.{$this->allergy->id}", 'peanuts')
        ->call('commit');

    expect($component->get('scopeViewIds'))
        ->toContain($this->thankYou->id)
        ->not->toContain($this->general->id);
});
