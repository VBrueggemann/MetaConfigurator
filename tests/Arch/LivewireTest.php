<?php

arch('livewire components never query the database directly')
    ->expect('App\Livewire')
    ->not->toUse([
        'Illuminate\Database\Eloquent\Builder',
        'Illuminate\Support\Facades\DB',
    ]);
