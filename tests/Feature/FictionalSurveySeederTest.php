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

    $evaluation = app(FetchConfigurationState::class)($this->configuration, [new PendingAnswer($this->allergy->id, null)]);

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

    // Vegan Extras' own Flow rule doesn't match for an omnivore respondent, so the
    // default (order-based, visibility-blind) fallback is what advances scope here.
    expect(scopeIds(app(FetchConfigurationState::class)($this->configuration)))
        ->toContain($this->veganExtras->id)
        ->not->toContain($this->thankYou->id);
});

it('advances directly to Vegan Extras for a vegan respondent, skipping General entirely', function () {
    app(CommitConfigurationAnswers::class)($this->configuration, [
        new PendingAnswer($this->aboutYou->id, true),
        new PendingAnswer($this->name->id, 'Ada'),
        new PendingAnswer($this->diet->id, 'vegan'),
        ...activityAnswers($this->activityViews, ['cooking']),
        new PendingAnswer($this->allergy->id, 'peanuts'),
    ]);

    // General carries a Flow rule mirroring its visibility condition, so it never
    // becomes the current view for a vegan respondent - scope goes straight to
    // Vegan Extras without ever needing to acknowledge General.
    expect(scopeIds(app(FetchConfigurationState::class)($this->configuration)))
        ->toContain($this->veganExtras->id)
        ->not->toContain($this->general->id)
        ->not->toContain($this->thankYou->id);

    app(CommitConfigurationAnswers::class)($this->configuration, [new PendingAnswer($this->veganExtras->id, true)]);

    expect(scopeIds(app(FetchConfigurationState::class)($this->configuration)))
        ->toContain($this->thankYou->id);
});

it('stays on Thank You once every reachable step has been acknowledged', function () {
    app(CommitConfigurationAnswers::class)($this->configuration, [
        new PendingAnswer($this->aboutYou->id, true),
        new PendingAnswer($this->name->id, 'Ada'),
        new PendingAnswer($this->diet->id, 'omnivore'),
        ...activityAnswers($this->activityViews, ['hiking']),
    ]);
    app(CommitConfigurationAnswers::class)($this->configuration, [new PendingAnswer($this->general->id, true)]);
    app(CommitConfigurationAnswers::class)($this->configuration, [new PendingAnswer($this->thankYou->id, true)]);

    $evaluation = app(FetchConfigurationState::class)($this->configuration);

    expect(scopeIds($evaluation))->toContain($this->thankYou->id);
});
