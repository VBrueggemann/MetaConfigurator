<?php

use App\Livewire\ConfiguratorAnalyticsPage;
use App\Livewire\PlayConfigurator;
use Illuminate\Support\Facades\Route;

Route::livewire('/configurators/{configurator}', PlayConfigurator::class)->name('configurators.play');
Route::livewire('/configurators/{configurator}/analytics', ConfiguratorAnalyticsPage::class)->name('configurators.analytics');
