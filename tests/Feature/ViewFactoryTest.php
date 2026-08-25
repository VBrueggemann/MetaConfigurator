<?php

use App\Enums\ViewType;
use App\Models\View;

it('only has three view type families', function () {
    expect(array_map(fn (ViewType $case) => $case->value, ViewType::cases()))
        ->toBe(['group', 'input', 'display']);
});

it('builds a text input as an Input view with a text widget', function () {
    $view = View::factory()->textInput('What is your name?')->create();

    expect($view->type)->toBe(ViewType::Input);
    expect($view->config['widget'])->toBe('text');
    expect($view->config['label'])->toBe('What is your name?');
});

it('builds a checkbox as an Input view defaulting to false', function () {
    $view = View::factory()->checkbox('Agree to terms?')->create();

    expect($view->type)->toBe(ViewType::Input);
    expect($view->config['widget'])->toBe('checkbox');
    expect($view->config['label'])->toBe('Agree to terms?');
    expect($view->config['default'])->toBeFalse();
});

it('builds a select as an Input view carrying its options', function () {
    $options = [['value' => 'a', 'label' => 'A'], ['value' => 'b', 'label' => 'B']];

    $view = View::factory()->select($options, 'Pick one')->create();

    expect($view->type)->toBe(ViewType::Input);
    expect($view->config['widget'])->toBe('select');
    expect($view->config['label'])->toBe('Pick one');
    expect($view->config['options'])->toBe($options);
});
