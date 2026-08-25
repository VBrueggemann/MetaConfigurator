<?php

namespace Database\Factories;

use App\Models\AnswerChunk;
use App\Models\Configuration;
use App\Models\View;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnswerChunk>
 */
class AnswerChunkFactory extends Factory
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
            'anchor_view_id' => View::factory(),
            'relevant' => true,
            'sequence' => 1,
        ];
    }
}
