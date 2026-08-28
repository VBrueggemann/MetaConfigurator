<?php

use App\Actions\CommitConfigurationAnswers;
use App\Actions\DTO\PendingAnswer;
use App\Actions\FetchConfigurationState;
use App\Models\Configuration;
use App\Models\Configurator;
use App\Models\Rule;
use App\Models\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Builds a root -> [groupOne(fields...), groupTwo, ...extra steps] tree with a
 * `just_next` Scope depth of 1, so a fresh Configuration anchors on groupOne.
 *
 * @return object{configuration: Configuration, fields: Collection<int, View>}
 */
function buildStepConfigurator(int $fieldCount = 3, int $extraStepCount = 0): object
{
    $configurator = Configurator::factory()->create();
    $root = View::factory()->group()->create(['configurator_id' => $configurator->id]);
    $groupOne = View::factory()->group()->childOf($root)->create(['order' => 0]);

    $fields = collect(range(0, $fieldCount - 1))->map(function (int $i) use ($groupOne) {
        $field = View::factory()->textInput("Field {$i}")->childOf($groupOne)->create(['order' => $i]);
        Rule::factory()->validation("answer('{$field->id}') != null")->create(['view_id' => $field->id]);

        return $field;
    });

    View::factory()->group()->childOf($root)->create(['order' => 1]);

    foreach (range(0, $extraStepCount - 1) as $i) {
        View::factory()->group()->childOf($root)->create(['order' => 2 + $i]);
    }

    $configurator->update(['scope_depth' => 1]);

    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);

    return (object) ['configuration' => $configuration, 'fields' => $fields];
}

function countQueries(Closure $callback): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $callback();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

it('runs the same number of read-pipeline queries no matter how many fields are visible in the current step', function () {
    $few = buildStepConfigurator(fieldCount: 2);
    $many = buildStepConfigurator(fieldCount: 12);

    $fewCount = countQueries(fn () => app(FetchConfigurationState::class)($few->configuration));
    $manyCount = countQueries(fn () => app(FetchConfigurationState::class)($many->configuration));

    expect($manyCount)->toBe($fewCount);
});

it('runs the same number of read-pipeline queries no matter how large the rest of the configurator tree is', function () {
    $small = buildStepConfigurator(fieldCount: 3, extraStepCount: 0);
    $large = buildStepConfigurator(fieldCount: 3, extraStepCount: 15);

    $smallCount = countQueries(fn () => app(FetchConfigurationState::class)($small->configuration));
    $largeCount = countQueries(fn () => app(FetchConfigurationState::class)($large->configuration));

    expect($largeCount)->toBe($smallCount);
});

it('runs the same number of read-pipeline queries no matter how many steps have already been answered', function () {
    $configurator = Configurator::factory()->create();
    $root = View::factory()->group()->create(['configurator_id' => $configurator->id]);
    $steps = collect(range(0, 19))->map(
        fn (int $i) => View::factory()->group()->childOf($root)->create(['order' => $i])
    );
    $configurator->update(['scope_depth' => 1]);

    $shortHistory = Configuration::factory()->create(['configurator_id' => $configurator->id]);
    $steps->take(2)->each(fn (View $step) => answer($shortHistory, $step, true));

    $longHistory = Configuration::factory()->create(['configurator_id' => $configurator->id]);
    $steps->take(10)->each(fn (View $step) => answer($longHistory, $step, true));

    $shortCount = countQueries(fn () => app(FetchConfigurationState::class)($shortHistory));
    $longCount = countQueries(fn () => app(FetchConfigurationState::class)($longHistory));

    expect($longCount)->toBe($shortCount);
});

it('runs the same number of write-pipeline queries for a single answer no matter how large the rest of the configurator tree is', function () {
    $small = buildStepConfigurator(fieldCount: 1, extraStepCount: 0);
    $large = buildStepConfigurator(fieldCount: 1, extraStepCount: 15);

    $smallCount = countQueries(fn () => app(CommitConfigurationAnswers::class)(
        $small->configuration,
        [new PendingAnswer($small->fields->first()->id, 'value')],
    ));
    $largeCount = countQueries(fn () => app(CommitConfigurationAnswers::class)(
        $large->configuration,
        [new PendingAnswer($large->fields->first()->id, 'value')],
    ));

    expect($largeCount)->toBe($smallCount);
});

it('runs the same number of write-pipeline queries no matter how many pending answers are committed at once', function () {
    $few = buildStepConfigurator(fieldCount: 2);
    $many = buildStepConfigurator(fieldCount: 12);

    $fewAnswers = $few->fields->map(fn (View $field) => new PendingAnswer($field->id, 'value'))->all();
    $manyAnswers = $many->fields->map(fn (View $field) => new PendingAnswer($field->id, 'value'))->all();

    $fewCount = countQueries(fn () => app(CommitConfigurationAnswers::class)($few->configuration, $fewAnswers));
    $manyCount = countQueries(fn () => app(CommitConfigurationAnswers::class)($many->configuration, $manyAnswers));

    expect($manyCount)->toBe($fewCount);
});
