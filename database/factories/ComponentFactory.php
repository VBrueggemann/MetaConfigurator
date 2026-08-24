<?php

namespace Database\Factories;

use App\Enums\ComponentType;
use App\Models\Component;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Component>
 */
class ComponentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => ComponentType::Visibility,
            'config' => [],
        ];
    }

    public function calculation(array $formulas): static
    {
        return $this->state(fn () => [
            'type' => ComponentType::Calculation,
            'config' => ['formulas' => $formulas],
        ]);
    }

    public function scope(int $depth, array $extra = []): static
    {
        return $this->state(fn () => [
            'type' => ComponentType::Scope,
            'config' => ['depth' => $depth, ...$extra],
        ]);
    }
}
