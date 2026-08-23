<?php

use App\Livewire\PlayConfigurator;
use Illuminate\Support\Facades\Route;

Route::livewire('/configurators/{configurator}', PlayConfigurator::class)->name('configurators.play');
