<?php

namespace Database\Factories;

use App\Models\Configuration;
use App\Models\Configurator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Configuration>
 */
class ConfigurationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'configurator_id' => Configurator::factory(),
        ];
    }
}
