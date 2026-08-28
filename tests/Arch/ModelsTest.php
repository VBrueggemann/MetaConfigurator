<?php

arch('domain models use UUID primary keys')
    ->expect('App\Models')
    ->toUseTrait('Illuminate\Database\Eloquent\Concerns\HasUuids')
    ->ignoring('App\Models\User');
