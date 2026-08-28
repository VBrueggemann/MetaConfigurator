<?php

use App\Actions\BuildConfiguratorAnalytics;
use App\Models\Configuration;
use App\Models\Configurator;
use App\Models\View;

it('aggregates answer distributions and transition counts per anchor view, ordered by first visit', function () {
    $configurator = Configurator::factory()->create();
    $stepOne = View::factory()->radio([
        ['value' => 'yes', 'label' => 'Yes'],
        ['value' => 'no', 'label' => 'No'],
    ], 'Do you own a car?')->create(['configurator_id' => $configurator->id]);
    $stepTwo = View::factory()->textInput('Which brand?')->create(['configurator_id' => $configurator->id]);

    $configurationA = Configuration::factory()->create(['configurator_id' => $configurator->id]);
    answer($configurationA, $stepOne, 'yes');
    answer($configurationA, $stepTwo, 'Toyota');

    $configurationB = Configuration::factory()->create(['configurator_id' => $configurator->id]);
    answer($configurationB, $stepOne, 'yes');
    answer($configurationB, $stepTwo, 'Honda');

    $configurationC = Configuration::factory()->create(['configurator_id' => $configurator->id]);
    answer($configurationC, $stepOne, 'no');

    $analytics = app(BuildConfiguratorAnalytics::class)($configurator);

    expect($analytics->totalConfigurations)->toBe(3);
    expect($analytics->anchors)->toHaveCount(2);

    $firstAnchor = $analytics->anchors->first();
    expect($firstAnchor->view->id)->toBe($stepOne->id);
    expect($firstAnchor->visitCount)->toBe(3);

    $answerValues = $firstAnchor->answers->first()['values'];
    expect($answerValues->firstWhere('value', 'yes'))->toMatchArray(['count' => 2, 'percentage' => 66.7]);
    expect($answerValues->firstWhere('value', 'no'))->toMatchArray(['count' => 1, 'percentage' => 33.3]);

    $transitionToStepTwo = $firstAnchor->transitions->first(fn (array $transition): bool => $transition['view']?->id === $stepTwo->id);
    expect($transitionToStepTwo)->toMatchArray(['count' => 2, 'percentage' => 66.7]);

    $finishedTransition = $firstAnchor->transitions->first(fn (array $transition): bool => $transition['view'] === null);
    expect($finishedTransition)->toMatchArray(['count' => 1, 'percentage' => 33.3]);

    $secondAnchor = $analytics->anchors->last();
    expect($secondAnchor->view->id)->toBe($stepTwo->id);
    expect($secondAnchor->visitCount)->toBe(2);
});

it('ignores superseded answer chunks', function () {
    $configurator = Configurator::factory()->create();
    $step = View::factory()->textInput()->create(['configurator_id' => $configurator->id]);
    $configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);

    answer($configuration, $step, 'first');
    $configuration->answerChunks()->first()->update(['relevant' => false]);
    answer($configuration, $step, 'second');

    $analytics = app(BuildConfiguratorAnalytics::class)($configurator);

    expect($analytics->anchors)->toHaveCount(1);
    expect($analytics->anchors->first()->visitCount)->toBe(1);
});

it('excludes configurations belonging to other configurators', function () {
    $configurator = Configurator::factory()->create();
    $otherConfigurator = Configurator::factory()->create();
    $step = View::factory()->textInput()->create(['configurator_id' => $otherConfigurator->id]);
    $configuration = Configuration::factory()->create(['configurator_id' => $otherConfigurator->id]);
    answer($configuration, $step, 'value');

    $analytics = app(BuildConfiguratorAnalytics::class)($configurator);

    expect($analytics->totalConfigurations)->toBe(0);
    expect($analytics->anchors)->toBeEmpty();
});
