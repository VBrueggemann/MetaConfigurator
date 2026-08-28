<?php

arch('queries are invokable')
    ->expect('App\Queries')
    ->toHaveMethod('__invoke');
