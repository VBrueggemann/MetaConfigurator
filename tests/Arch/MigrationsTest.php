<?php

it('never defines a down() method', function (string $path) {
    $migration = require $path;

    expect(method_exists($migration, 'down'))->toBeFalse();
})->with(function () {
    foreach (glob(__DIR__.'/../../database/migrations/*.php') as $path) {
        yield basename($path) => $path;
    }
});
