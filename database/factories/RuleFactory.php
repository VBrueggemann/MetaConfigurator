<?php

namespace Database\Factories;

use App\Enums\RuleComponent;
use App\Models\Rule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Rule>
 */
class RuleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'view_id' => null,
            'component' => RuleComponent::Visibility,
            'expression' => 'true',
            'meta' => null,
            'order' => 0,
        ];
    }

    public function visibility(string $expression): static
    {
        return $this->state(fn () => ['component' => RuleComponent::Visibility, 'expression' => $expression]);
    }

    public function validation(string $expression): static
    {
        return $this->state(fn () => ['component' => RuleComponent::Validation, 'expression' => $expression]);
    }
}
