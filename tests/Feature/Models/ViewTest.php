<?php

use App\Models\Configurator;
use App\Models\View;

it('resolves all nested descendants, not just direct children', function () {
    $configurator = Configurator::factory()->create();
    $root = View::factory()->group()->create(['configurator_id' => $configurator->id]);
    $child = View::factory()->group()->childOf($root)->create();
    $grandchild = View::factory()->textInput()->childOf($child)->create();

    expect($root->descendants->pluck('id')->all())->toEqualCanonicalizing([$child->id, $grandchild->id]);
});

it('resolves all ancestors up the tree, not just the direct parent', function () {
    $configurator = Configurator::factory()->create();
    $root = View::factory()->group()->create(['configurator_id' => $configurator->id]);
    $child = View::factory()->group()->childOf($root)->create();
    $grandchild = View::factory()->textInput()->childOf($child)->create();

    expect($grandchild->ancestors->pluck('id')->all())->toEqualCanonicalizing([$root->id, $child->id]);
});

it('still orders children by their order column', function () {
    $configurator = Configurator::factory()->create();
    $root = View::factory()->group()->create(['configurator_id' => $configurator->id]);
    $second = View::factory()->textInput()->childOf($root)->create(['order' => 1]);
    $first = View::factory()->textInput()->childOf($root)->create(['order' => 0]);

    expect($root->children->pluck('id')->all())->toBe([$first->id, $second->id]);
});
