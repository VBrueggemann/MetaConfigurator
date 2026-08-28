<?php

namespace Database\Factories;

use App\Models\Calculation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Calculation>
 */
class CalculationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'expression' => '1',
        ];
    }
}
