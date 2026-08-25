<?php

use App\Actions\DTO\PendingAnswer;
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

    $root = $configurator->rootView;
    $groups = View::where('parent_id', $root->id)->orderBy('order')->get();
    $this->aboutYou = $groups[0];
    $this->normalEnd = $groups[1];
    $this->allergyEnd = $groups[2];

    $items = View::where('parent_id', $this->aboutYou->id)->orderBy('order')->get();
    $this->name = $items[0];
    $this->diet = $items[1];
    $this->allergy = $items[2];
});

it('hides the allergy question for a non-vegan respondent', function () {
    answer($this->configuration, $this->diet, 'omnivore');

    $evaluation = app(FetchConfigurationState::class)($this->configuration);

    expect($evaluation->componentContexts['visibility'][$this->allergy->id])->toBeFalse();
});

it('shows and requires the allergy question for a vegan respondent', function () {
    $evaluation = app(FetchConfigurationState::class)($this->configuration, [
        new PendingAnswer($this->diet->id, 'vegan'),
        new PendingAnswer($this->allergy->id, null),
    ]);

    expect($evaluation->componentContexts['visibility'][$this->allergy->id])->toBeTrue();
    expect($evaluation->componentContexts['validation'])->toHaveKey($this->allergy->id);
});

it('rejects a name over 50 characters', function () {
    $evaluation = app(CommitConfigurationAnswers::class)($this->configuration, [
        new PendingAnswer($this->aboutYou->id, true),
        new PendingAnswer($this->name->id, str_repeat('a', 51)),
        new PendingAnswer($this->diet->id, 'omnivore'),
    ]);

    expect($evaluation->componentContexts['validation'])->toHaveKey($this->name->id);
});

it('does not advance past About You while required fields are missing', function () {
    app(CommitConfigurationAnswers::class)($this->configuration, [
        new PendingAnswer($this->aboutYou->id, true),
        new PendingAnswer($this->name->id, 'Ada'),
        new PendingAnswer($this->diet->id, null),
    ]);

    expect(scopeIds(app(FetchConfigurationState::class)($this->configuration)))
        ->toContain($this->aboutYou->id)
        ->not->toContain($this->normalEnd->id)
        ->not->toContain($this->allergyEnd->id);
});

it('advances to the normal ending for a respondent without an allergy', function () {
    app(CommitConfigurationAnswers::class)($this->configuration, [
        new PendingAnswer($this->aboutYou->id, true),
        new PendingAnswer($this->name->id, 'Ada'),
        new PendingAnswer($this->diet->id, 'omnivore'),
    ]);

    expect(scopeIds(app(FetchConfigurationState::class)($this->configuration)))
        ->toContain($this->normalEnd->id)
        ->not->toContain($this->allergyEnd->id);
});

it('advances to the allergy ending for a vegan respondent with an allergy', function () {
    app(CommitConfigurationAnswers::class)($this->configuration, [
        new PendingAnswer($this->aboutYou->id, true),
        new PendingAnswer($this->name->id, 'Ada'),
        new PendingAnswer($this->diet->id, 'vegan'),
        new PendingAnswer($this->allergy->id, 'peanuts'),
    ]);

    expect(scopeIds(app(FetchConfigurationState::class)($this->configuration)))
        ->toContain($this->allergyEnd->id)
        ->not->toContain($this->normalEnd->id);
});
