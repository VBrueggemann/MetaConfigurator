<?php

use App\Actions\GetCurrentConfiguration;
use App\Models\Configuration;
use App\Models\Configurator;
use Illuminate\Support\Str;

it('creates a new configuration when none exists in the session', function () {
    $configurator = Configurator::factory()->create();

    $configuration = app(GetCurrentConfiguration::class)($configurator);

    expect(Configuration::count())->toBe(1);
    expect($configuration->configurator_id)->toBe($configurator->id);
    expect(session("metaconfigurator.configuration.{$configurator->id}"))->toBe($configuration->id);
});

it('returns the same configuration on a later call', function () {
    $configurator = Configurator::factory()->create();

    $first = app(GetCurrentConfiguration::class)($configurator);
    $second = app(GetCurrentConfiguration::class)($configurator);

    expect(Configuration::count())->toBe(1);
    expect($second->id)->toBe($first->id);
});

it('creates a new configuration when the session references one that no longer exists', function () {
    $configurator = Configurator::factory()->create();
    session(["metaconfigurator.configuration.{$configurator->id}" => (string) Str::uuid()]);

    $configuration = app(GetCurrentConfiguration::class)($configurator);

    expect(Configuration::count())->toBe(1);
    expect(session("metaconfigurator.configuration.{$configurator->id}"))->toBe($configuration->id);
});

it('creates a new configuration when the session id belongs to another configurator', function () {
    $configurator = Configurator::factory()->create();
    $otherConfigurator = Configurator::factory()->create();
    $otherConfiguration = Configuration::factory()->create(['configurator_id' => $otherConfigurator->id]);
    session(["metaconfigurator.configuration.{$configurator->id}" => $otherConfiguration->id]);

    $configuration = app(GetCurrentConfiguration::class)($configurator);

    expect(Configuration::count())->toBe(2);
    expect($configuration->id)->not->toBe($otherConfiguration->id);
    expect($configuration->configurator_id)->toBe($configurator->id);
    expect(session("metaconfigurator.configuration.{$configurator->id}"))->toBe($configuration->id);
});
