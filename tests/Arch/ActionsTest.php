<?php

arch('actions are invokable')
    ->expect('App\Actions')
    ->toHaveMethod('__invoke')
    ->ignoring('App\Actions\DTO');

arch('DTOs are not invokable')
    ->expect('App\Actions\DTO')
    ->not->toHaveMethod('__invoke');
