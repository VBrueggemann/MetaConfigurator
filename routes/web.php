<?php

use App\Livewire\PlayConfigurator;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::livewire('/configurators/{configurator}', PlayConfigurator::class)->name('configurators.play');
