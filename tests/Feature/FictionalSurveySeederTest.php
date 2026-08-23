<?php

use App\Actions\DTO\PendingAnswer;
use App\Actions\Rules\CommitConfigurationAnswers;
use App\Actions\Rules\FetchConfigurationState;
use App\Enums\ViewType;
use App\Models\Configuration;
use App\Models\Configurator;
use App\Models\View;
use Database\Seeders\FictionalSurveySeeder;

/**
 * @param  array<string, View>  $activityViews
 * @param  list<string>  $selected
 * @return list<PendingAnswer>
 */
function activityAnswers(array $activityViews, array $selected): array
{
    return array_map(
        fn (string $key, View $view): PendingAnswer => new PendingAnswer($view->id, in_array($key, $selected, true)),
        array_keys($activityViews),
        array_values($activityViews),
    );
}

beforeEach(function () {
    app(FictionalSurveySeeder::class)->run();

    $configurator = Configurator::where('name', 'Dinner Party Survey')->firstOrFail();
    $this->configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);

    $root = $configurator->rootView;
    $groups = View::where('parent_id', $root->id)->orderBy('order')->get();
    $this->aboutYou = $groups[0];
    $this->general = $groups[1];
    $this->veganExtras = $groups[2];
    $this->thankYou = $groups[3];

    $items = View::where('configurator_id', $configurator->id)->where('parent_id', $this->aboutYou->id)->orderBy('order')->get();
    $this->name = $items[0];
    $this->diet = $items[1];
    $this->activities = $items[2];
    $this->allergy = $items[3];

    $activityItems = View::where('parent_id', $this->activities->id)->where('type', ViewType::Checkbox)->orderBy('order')->get();
    $this->activityViews = [
        'hiking' => $activityItems[0],
        'board_games' => $activityItems[1],
        'karaoke' => $activityItems[2],
        'cooking' => $activityItems[3],
    ];
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

it('rejects zero and more than three activities, accepts one to three', function (array $selected, bool $valid) {
    $evaluation = app(CommitConfigurationAnswers::class)($this->configuration, [
        new PendingAnswer($this->aboutYou->id, true),
        new PendingAnswer($this->name->id, 'Ada'),
        new PendingAnswer($this->diet->id, 'omnivore'),
        ...activityAnswers($this->activityViews, $selected),
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
        new PendingAnswer($this->aboutYou->id, true),
        new PendingAnswer($this->name->id, str_repeat('a', 51)),
        new PendingAnswer($this->diet->id, 'omnivore'),
        ...activityAnswers($this->activityViews, ['hiking']),
    ]);

    expect($evaluation->componentContexts['validation'])->toHaveKey($this->name->id);
});

it('advances scope to Vegan Extras next, even though it is hidden for a non-vegan respondent', function () {
    app(CommitConfigurationAnswers::class)($this->configuration, [
        new PendingAnswer($this->aboutYou->id, true),
        new PendingAnswer($this->name->id, 'Ada'),
        new PendingAnswer($this->diet->id, 'omnivore'),
        ...activityAnswers($this->activityViews, ['hiking']),
    ]);

    expect(scopeIds(app(FetchConfigurationState::class)($this->configuration)))
        ->toContain($this->general->id);

    app(CommitConfigurationAnswers::class)($this->configuration, [new PendingAnswer($this->general->id, true)]);

    // Scope has no visibility awareness, so it advances to the next unanswered
    // step regardless of Vegan Extras being hidden for a non-vegan respondent.
    expect(scopeIds(app(FetchConfigurationState::class)($this->configuration)))
        ->toContain($this->veganExtras->id)
        ->not->toContain($this->thankYou->id);
});

it('advances through General to Vegan Extras in step order, even for a vegan respondent', function () {
    app(CommitConfigurationAnswers::class)($this->configuration, [
        new PendingAnswer($this->aboutYou->id, true),
        new PendingAnswer($this->name->id, 'Ada'),
        new PendingAnswer($this->diet->id, 'vegan'),
        ...activityAnswers($this->activityViews, ['cooking']),
        new PendingAnswer($this->allergy->id, 'peanuts'),
    ]);

    // Scope steps through General before Vegan Extras purely by order,
    // regardless of General being hidden for a vegan respondent.
    expect(scopeIds(app(FetchConfigurationState::class)($this->configuration)))
        ->toContain($this->general->id)
        ->not->toContain($this->veganExtras->id);

    app(CommitConfigurationAnswers::class)($this->configuration, [new PendingAnswer($this->general->id, true)]);

    expect(scopeIds(app(FetchConfigurationState::class)($this->configuration)))
        ->toContain($this->veganExtras->id)
        ->not->toContain($this->thankYou->id);
});

it('stays on Thank You once every step, including the hidden one, has been acknowledged', function () {
    app(CommitConfigurationAnswers::class)($this->configuration, [
        new PendingAnswer($this->aboutYou->id, true),
        new PendingAnswer($this->name->id, 'Ada'),
        new PendingAnswer($this->diet->id, 'omnivore'),
        ...activityAnswers($this->activityViews, ['hiking']),
    ]);
    app(CommitConfigurationAnswers::class)($this->configuration, [new PendingAnswer($this->general->id, true)]);
    app(CommitConfigurationAnswers::class)($this->configuration, [new PendingAnswer($this->veganExtras->id, true)]);
    app(CommitConfigurationAnswers::class)($this->configuration, [new PendingAnswer($this->thankYou->id, true)]);

    $evaluation = app(FetchConfigurationState::class)($this->configuration);

    expect(scopeIds($evaluation))->toContain($this->thankYou->id);
});
