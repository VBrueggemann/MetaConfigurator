<?php

use App\Livewire\ConfiguratorAnalyticsPage;
use App\Models\Configuration;
use App\Models\Configurator;
use App\Models\View;
use Livewire\Livewire;

it('renders answer percentages and next-step transitions for each anchor view', function () {
    $configurator = Configurator::factory()->create(['name' => 'Dinner Party Survey']);
    $diet = View::factory()->radio([
        ['value' => 'vegan', 'label' => 'Vegan'],
        ['value' => 'omnivore', 'label' => 'Omnivore'],
    ], 'Diet')->create(['configurator_id' => $configurator->id]);
    $allergy = View::factory()->textInput('Allergies')->create(['configurator_id' => $configurator->id]);

    $configurationA = Configuration::factory()->create(['configurator_id' => $configurator->id]);
    answer($configurationA, $diet, 'vegan');
    answer($configurationA, $allergy, 'none');

    $configurationB = Configuration::factory()->create(['configurator_id' => $configurator->id]);
    answer($configurationB, $diet, 'omnivore');

    Livewire::test(ConfiguratorAnalyticsPage::class, ['configurator' => $configurator])
        ->assertSee('Dinner Party Survey')
        ->assertSee('2 configurations total')
        ->assertSee('Vegan')
        ->assertSee('Omnivore')
        ->assertSee('50%')
        ->assertSee('Finished the configurator');
});
