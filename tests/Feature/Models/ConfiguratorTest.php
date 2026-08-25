<?php

use App\Models\Configurator;

it('derives a slug from the name when none is given', function () {
    $configurator = Configurator::factory()->create(['name' => 'Widget Showcase']);

    expect($configurator->slug)->toBe('widget-showcase');
});

it('keeps an explicitly given slug', function () {
    $configurator = Configurator::factory()->create(['name' => 'Widget Showcase', 'slug' => 'custom-slug']);

    expect($configurator->slug)->toBe('custom-slug');
});

it('disambiguates derived slugs that collide', function () {
    Configurator::factory()->create(['name' => 'Widget Showcase']);
    $second = Configurator::factory()->create(['name' => 'Widget Showcase']);

    expect($second->slug)->toBe('widget-showcase-2');
});

it('uses the slug as the route key', function () {
    $configurator = Configurator::factory()->create(['slug' => 'custom-slug']);

    expect($configurator->getRouteKey())->toBe('custom-slug');
});
