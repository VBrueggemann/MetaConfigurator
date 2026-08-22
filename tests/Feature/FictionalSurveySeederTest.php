<?php

use App\Actions\Rules\EvaluateGroup;
use App\Models\Answer;
use App\Models\Configuration;
use App\Models\Configurator;
use App\Models\View;
use Database\Seeders\FictionalSurveySeeder;

beforeEach(function () {
    app(FictionalSurveySeeder::class)->run();

    $configurator = Configurator::where('name', 'Dinner Party Survey')->firstOrFail();
    $this->configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);

    $groups = View::where('configurator_id', $configurator->id)->whereNull('parent_id')->orderBy('order')->get();
    $this->aboutYou = $groups[0];
    $this->general = $groups[1];
    $this->veganExtras = $groups[2];
    $this->thankYou = $groups[3];

    $items = View::where('configurator_id', $configurator->id)->where('parent_id', $this->aboutYou->id)->orderBy('order')->get();
    $this->name = $items[0];
    $this->diet = $items[1];
    $this->activities = $items[2];
    $this->allergy = $items[3];
});

function answer($configuration, $view, $value)
{
    Answer::factory()->create([
        'configuration_id' => $configuration->id,
        'view_id' => $view->id,
        'value' => $value,
    ]);
}

it('hides the allergy question and routes to General for a non-vegan respondent', function () {
    answer($this->configuration, $this->name, 'Ada');
    answer($this->configuration, $this->diet, 'omnivore');
    answer($this->configuration, $this->activities, ['hiking']);

    $evaluation = app(EvaluateGroup::class)($this->aboutYou, $this->configuration);

    expect($evaluation->componentContexts['visibility'])->not->toContain($this->allergy->id);
    expect($evaluation->componentContexts['validation'])->toBe([]);
    expect($evaluation->componentContexts['next_group'])->toBe($this->general->id);
});

it('shows and requires the allergy question, then routes to Vegan Extras', function () {
    answer($this->configuration, $this->name, 'Ada');
    answer($this->configuration, $this->diet, 'vegan');
    answer($this->configuration, $this->activities, ['cooking']);

    $evaluation = app(EvaluateGroup::class)($this->aboutYou, $this->configuration);

    expect($evaluation->componentContexts['visibility'])->toContain($this->allergy->id);
    expect($evaluation->componentContexts['validation'])->toHaveKey($this->allergy->id);
    expect($evaluation->componentContexts['next_group'])->toBeNull();

    answer($this->configuration, $this->allergy, 'peanuts');

    $evaluation = app(EvaluateGroup::class)($this->aboutYou, $this->configuration);

    expect($evaluation->componentContexts['validation'])->toBe([]);
    expect($evaluation->componentContexts['next_group'])->toBe($this->veganExtras->id);
});

it('rejects zero and more than three activities, accepts one to three', function (array $value, bool $valid) {
    answer($this->configuration, $this->name, 'Ada');
    answer($this->configuration, $this->diet, 'omnivore');
    answer($this->configuration, $this->activities, $value);

    $evaluation = app(EvaluateGroup::class)($this->aboutYou, $this->configuration);

    expect(array_key_exists($this->activities->id, $evaluation->componentContexts['validation']))->toBe(! $valid);
})->with([
    'zero' => [[], false],
    'one' => [['hiking'], true],
    'three' => [['hiking', 'board_games', 'karaoke'], true],
    'four' => [['hiking', 'board_games', 'karaoke', 'cooking'], false],
]);

it('rejects a name over 50 characters', function () {
    answer($this->configuration, $this->name, str_repeat('a', 51));
    answer($this->configuration, $this->diet, 'omnivore');
    answer($this->configuration, $this->activities, ['hiking']);

    $evaluation = app(EvaluateGroup::class)($this->aboutYou, $this->configuration);

    expect($evaluation->componentContexts['validation'])->toHaveKey($this->name->id);
});

it('routes General to Thank You, skipping Vegan Extras', function () {
    $evaluation = app(EvaluateGroup::class)($this->general, $this->configuration);

    expect($evaluation->componentContexts['next_group'])->toBe($this->thankYou->id);
});

it('routes Vegan Extras to Thank You sequentially', function () {
    $evaluation = app(EvaluateGroup::class)($this->veganExtras, $this->configuration);

    expect($evaluation->componentContexts['next_group'])->toBe($this->thankYou->id);
});

it('has no next group after Thank You', function () {
    $evaluation = app(EvaluateGroup::class)($this->thankYou, $this->configuration);

    expect($evaluation->componentContexts['next_group'])->toBeNull();
});
