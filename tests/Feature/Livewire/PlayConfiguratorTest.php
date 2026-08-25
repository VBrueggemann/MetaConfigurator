<?php

use App\Livewire\PlayConfigurator;
use App\Models\Answer;
use App\Models\Component;
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
    $this->normalEnd = $groups[1];
    $this->allergyEnd = $groups[2];

    $items = View::where('configurator_id', $this->configurator->id)->where('parent_id', $this->aboutYou->id)->orderBy('order')->get();
    $this->name = $items[0];
    $this->diet = $items[1];
    $this->allergy = $items[2];
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
        ->not->toContain($this->normalEnd->id)
        ->not->toContain($this->allergyEnd->id);
    expect($component->get('visibleViewIds'))
        ->toContain($this->allergy->id);
});

it('advances to the normal ending for a respondent without an allergy', function () {
    $component = Livewire::test(PlayConfigurator::class, ['configurator' => $this->configurator])
        ->set("values.{$this->name->id}", 'Ada')
        ->set("values.{$this->diet->id}", 'omnivore')
        ->call('next');

    expect($component->get('scopeViewIds'))
        ->toContain($this->normalEnd->id)
        ->not->toContain($this->allergyEnd->id);
});

it('advances to the allergy ending for a vegan respondent with an allergy', function () {
    $component = Livewire::test(PlayConfigurator::class, ['configurator' => $this->configurator])
        ->set("values.{$this->name->id}", 'Ada')
        ->set("values.{$this->diet->id}", 'vegan')
        ->set("values.{$this->allergy->id}", 'peanuts')
        ->call('next');

    expect($component->get('scopeViewIds'))
        ->toContain($this->allergyEnd->id)
        ->not->toContain($this->normalEnd->id);
});

it('shows a diet validation error when clicking Next without a diet', function () {
    $component = Livewire::test(PlayConfigurator::class, ['configurator' => $this->configurator])
        ->set("values.{$this->name->id}", 'Ada')
        ->call('next');

    $component->assertSee('Please tell us your diet.');
});

it('renders the next screen and persists answers after a fully valid commit', function () {
    $component = Livewire::test(PlayConfigurator::class, ['configurator' => $this->configurator])
        ->set("values.{$this->name->id}", 'Ada')
        ->set("values.{$this->diet->id}", 'omnivore')
        ->call('next');

    $component->assertDontSee('What is your name?');
    $component->assertSee('Thanks!', false);
    expect(Answer::count())->toBeGreaterThan(0);
});

it('allows returning to an earlier step, editing an answer, and re-committing changes the outcome', function () {
    $component = Livewire::test(PlayConfigurator::class, ['configurator' => $this->configurator])
        ->set("values.{$this->name->id}", 'Ada')
        ->set("values.{$this->diet->id}", 'omnivore')
        ->call('next');

    expect($component->get('scopeViewIds'))->toContain($this->normalEnd->id);

    $component->call('goToView', $this->aboutYou->id);

    expect($component->get('scopeViewIds'))->toContain($this->aboutYou->id);
    expect($component->get('values')[$this->name->id])->toBe('Ada');

    $component->set("values.{$this->diet->id}", 'vegan')
        ->set("values.{$this->allergy->id}", 'peanuts')
        ->call('next');

    expect($component->get('scopeViewIds'))
        ->toContain($this->allergyEnd->id)
        ->not->toContain($this->normalEnd->id);
});

it('does not advance away from a manually revisited step on live edits', function () {
    $component = Livewire::test(PlayConfigurator::class, ['configurator' => $this->configurator])
        ->set("values.{$this->name->id}", 'Ada')
        ->set("values.{$this->diet->id}", 'omnivore')
        ->call('next')
        ->call('goToView', $this->aboutYou->id)
        ->set("values.{$this->diet->id}", 'vegan');

    expect($component->get('scopeViewIds'))
        ->toContain($this->aboutYou->id)
        ->not->toContain($this->normalEnd->id)
        ->not->toContain($this->allergyEnd->id);
});

it('prefills a superseded step field with its stale answer instead of the static config default', function () {
    $configurator = Configurator::factory()->create();
    $root = View::factory()->group()->create(['configurator_id' => $configurator->id]);
    $stepOne = View::factory()->group()->childOf($root)->create(['order' => 0]);
    $fieldOne = View::factory()->textInput('Field one')->childOf($stepOne)->create(['order' => 0]);
    $stepTwo = View::factory()->group()->childOf($root)->create(['order' => 1]);
    $fieldTwo = View::factory()->textInput('Field two')->childOf($stepTwo)->create(['order' => 0]);
    Component::factory()->scope(depth: 1)->create(['configurator_id' => $configurator->id]);

    $component = Livewire::test(PlayConfigurator::class, ['configurator' => $configurator])
        ->set("values.{$fieldOne->id}", 'first answer')
        ->call('next')
        ->set("values.{$fieldTwo->id}", 'second answer')
        ->call('next')
        ->call('goToView', $stepOne->id)
        ->set("values.{$fieldOne->id}", 'changed answer')
        ->call('next');

    expect($component->get('values')[$fieldTwo->id])->toBe('second answer');
});

it('renders a clickable breadcrumb for each already-answered step', function () {
    $configurator = Configurator::factory()->create();
    $root = View::factory()->group()->create(['configurator_id' => $configurator->id]);
    $stepOne = View::factory()->group('Step One')->childOf($root)->create(['order' => 0]);
    $fieldOne = View::factory()->textInput('Field one')->childOf($stepOne)->create(['order' => 0]);
    View::factory()->group('Step Two')->childOf($root)->create(['order' => 1]);
    Component::factory()->scope(depth: 1)->create(['configurator_id' => $configurator->id]);

    $component = Livewire::test(PlayConfigurator::class, ['configurator' => $configurator])
        ->set("values.{$fieldOne->id}", 'answer')
        ->call('next');

    $component->assertSee('Step One');
    $component->assertSeeHtml("wire:click=\"goToView('{$stepOne->id}')\"");
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
