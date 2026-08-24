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

it('does not persist an answer until next is called', function () {
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

    $component->call('next');

    expect($component->get('scopeViewIds'))->toContain($this->aboutYou->id);
    expect(Answer::count())->toBe(0);
});

it('does not advance scope on live edits, only on an explicit commit', function () {
    $component = Livewire::test(PlayConfigurator::class, ['configurator' => $this->configurator])
        ->set("values.{$this->name->id}", 'Ada')
        ->set("values.{$this->diet->id}", 'vegan');

    expect($component->get('scopeViewIds'))
        ->toContain($this->aboutYou->id)
        ->not->toContain($this->veganExtras->id);
    expect($component->get('visibleViewIds'))
        ->toContain($this->activities->id)
        ->toContain($this->allergy->id);
});

it('walks a non-vegan respondent to Vegan Extras, acknowledging General on a second click', function () {
    $component = Livewire::test(PlayConfigurator::class, ['configurator' => $this->configurator])
        ->set("values.{$this->name->id}", 'Ada')
        ->set("values.{$this->diet->id}", 'omnivore')
        ->set("values.{$this->hiking->id}", true)
        ->call('next');

    expect($component->get('scopeViewIds'))
        ->toContain($this->general->id)
        ->not->toContain($this->veganExtras->id);

    $component->call('next');

    expect($component->get('scopeViewIds'))
        ->toContain($this->veganExtras->id)
        ->not->toContain($this->general->id);
});

it('walks a vegan respondent to Thank You, skipping General and acknowledging Vegan Extras on a second click', function () {
    $component = Livewire::test(PlayConfigurator::class, ['configurator' => $this->configurator])
        ->set("values.{$this->name->id}", 'Ada')
        ->set("values.{$this->diet->id}", 'vegan')
        ->set("values.{$this->cooking->id}", true)
        ->set("values.{$this->allergy->id}", 'peanuts')
        ->call('next');

    expect($component->get('scopeViewIds'))
        ->toContain($this->veganExtras->id)
        ->not->toContain($this->general->id)
        ->not->toContain($this->thankYou->id);

    $component->call('next');

    expect($component->get('scopeViewIds'))
        ->toContain($this->thankYou->id)
        ->not->toContain($this->veganExtras->id);
});

it('shows the activities count error when clicking Next with zero activities checked', function () {
    $component = Livewire::test(PlayConfigurator::class, ['configurator' => $this->configurator])
        ->set("values.{$this->name->id}", 'Ada')
        ->set("values.{$this->diet->id}", 'omnivore')
        ->call('next');

    $component->assertSee('Choose at least one activity.');
});

it('renders the next screen and persists answers after a fully valid commit', function () {
    $component = Livewire::test(PlayConfigurator::class, ['configurator' => $this->configurator])
        ->set("values.{$this->name->id}", 'Ada')
        ->set("values.{$this->diet->id}", 'omnivore')
        ->set("values.{$this->hiking->id}", true)
        ->call('next');

    $component->assertDontSee('What is your name?');
    $component->assertSee('Thanks!', false);
    expect(Answer::count())->toBeGreaterThan(0);
});

it('does not name a public action after one of Livewire\'s reserved $wire magic actions', function () {
    // wire:click="commit" silently resolves to the built-in $wire.commit() no-op instead of
    // calling our method, because the frontend aliases these bare names before ever hitting
    // the server — Livewire::test()->call() bypasses that alias table, so it can't catch this.
    $reservedWireAliases = [
        'on', 'el', 'id', 'js', 'get', 'set', 'refs', 'call', 'hook', 'watch', 'dirty',
        'effect', 'commit', 'errors', 'island', 'upload', 'entangle', 'dispatch', 'intercept',
        'interceptAction', 'interceptMessage', 'interceptRequest', 'dispatchTo', 'dispatchSelf',
        'dispatchEl', 'dispatchRef', 'removeUpload', 'cancelUpload', 'uploadMultiple',
    ];

    $ownPublicMethods = collect((new ReflectionClass(PlayConfigurator::class))->getMethods(ReflectionMethod::IS_PUBLIC))
        ->filter(fn (ReflectionMethod $method): bool => $method->getDeclaringClass()->getName() === PlayConfigurator::class)
        ->map(fn (ReflectionMethod $method): string => $method->getName())
        ->reject(fn (string $method): bool => in_array($method, ['mount', 'render', 'updatedValues'], true));

    expect($ownPublicMethods->intersect($reservedWireAliases))->toBeEmpty();
});
