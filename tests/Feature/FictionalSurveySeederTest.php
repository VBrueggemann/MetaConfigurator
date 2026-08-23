<?php

use App\Actions\Rules\CommitConfigurationAnswers;
use App\Actions\Rules\FetchConfigurationState;
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

it('hides the allergy question for a non-vegan respondent', function () {
    answer($this->configuration, $this->diet, 'omnivore');

    $evaluation = app(FetchConfigurationState::class)($this->configuration);

    expect($evaluation->componentContexts['visibility'][$this->allergy->id])->toBeFalse();
});

it('shows and requires the allergy question for a vegan respondent', function () {
    answer($this->configuration, $this->diet, 'vegan');

    $evaluation = app(FetchConfigurationState::class)($this->configuration);

    expect($evaluation->componentContexts['visibility'][$this->allergy->id])->toBeTrue();
    expect($evaluation->componentContexts['validation'])->toHaveKey($this->allergy->id);
});

it('rejects zero and more than three activities, accepts one to three', function (array $value, bool $valid) {
    $evaluation = app(CommitConfigurationAnswers::class)($this->configuration, [
        $this->aboutYou->id => true,
        $this->name->id => 'Ada',
        $this->diet->id => 'omnivore',
        $this->activities->id => $value,
    ]);

    expect(array_key_exists($this->activities->id, $evaluation->componentContexts['validation']))->toBe(! $valid);
})->with([
    'zero' => [[], false],
    'one' => [['hiking'], true],
    'three' => [['hiking', 'board_games', 'karaoke'], true],
    'four' => [['hiking', 'board_games', 'karaoke', 'cooking'], false],
]);

it('rejects a name over 50 characters', function () {
    $evaluation = app(CommitConfigurationAnswers::class)($this->configuration, [
        $this->aboutYou->id => true,
        $this->name->id => str_repeat('a', 51),
        $this->diet->id => 'omnivore',
        $this->activities->id => ['hiking'],
    ]);

    expect($evaluation->componentContexts['validation'])->toHaveKey($this->name->id);
});

it('routes a non-vegan respondent through General to Thank You, skipping Vegan Extras', function () {
    app(CommitConfigurationAnswers::class)($this->configuration, [
        $this->aboutYou->id => true,
        $this->name->id => 'Ada',
        $this->diet->id => 'omnivore',
        $this->activities->id => ['hiking'],
    ]);

    expect(app(FetchConfigurationState::class)($this->configuration)->componentContexts['scope'])
        ->toContain($this->general->id);

    app(CommitConfigurationAnswers::class)($this->configuration, [$this->general->id => true]);

    expect(app(FetchConfigurationState::class)($this->configuration)->componentContexts['scope'])
        ->toContain($this->thankYou->id)
        ->not->toContain($this->veganExtras->id);
});

it('routes a vegan respondent through Vegan Extras to Thank You, skipping General', function () {
    app(CommitConfigurationAnswers::class)($this->configuration, [
        $this->aboutYou->id => true,
        $this->name->id => 'Ada',
        $this->diet->id => 'vegan',
        $this->activities->id => ['cooking'],
        $this->allergy->id => 'peanuts',
    ]);

    expect(app(FetchConfigurationState::class)($this->configuration)->componentContexts['scope'])
        ->toContain($this->veganExtras->id);

    app(CommitConfigurationAnswers::class)($this->configuration, [$this->veganExtras->id => true]);

    expect(app(FetchConfigurationState::class)($this->configuration)->componentContexts['scope'])
        ->toContain($this->thankYou->id)
        ->not->toContain($this->general->id);
});

it('stays on Thank You once it has been acknowledged', function () {
    app(CommitConfigurationAnswers::class)($this->configuration, [
        $this->aboutYou->id => true,
        $this->name->id => 'Ada',
        $this->diet->id => 'omnivore',
        $this->activities->id => ['hiking'],
    ]);
    app(CommitConfigurationAnswers::class)($this->configuration, [$this->general->id => true]);
    app(CommitConfigurationAnswers::class)($this->configuration, [$this->thankYou->id => true]);

    $evaluation = app(FetchConfigurationState::class)($this->configuration);

    expect($evaluation->componentContexts['scope'])->toContain($this->thankYou->id);
});
