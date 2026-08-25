<?php

namespace Database\Factories;

use App\Models\Answer;
use App\Models\AnswerChunk;
use App\Models\Configuration;
use App\Models\View;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Answer>
 */
class AnswerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'configuration_id' => Configuration::factory(),
            'view_id' => View::factory(),
            'answer_chunk_id' => AnswerChunk::factory(),
            'value' => fake()->word(),
        ];
    }
}
