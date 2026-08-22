<?php

namespace Database\Factories;

use App\Enums\ViewType;
use App\Models\Configurator;
use App\Models\View;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<View>
 */
class ViewFactory extends Factory
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
            'parent_id' => null,
            'type' => ViewType::Group,
            'order' => 0,
            'config' => [],
        ];
    }

    public function group(): static
    {
        return $this->state(fn () => ['type' => ViewType::Group]);
    }

    public function select(array $options, bool $multiple = false, ?string $label = null): static
    {
        return $this->state(fn () => [
            'type' => ViewType::Select,
            'config' => ['label' => $label ?? fake()->sentence(), 'multiple' => $multiple, 'options' => $options],
        ]);
    }

    public function textInput(?string $label = null): static
    {
        return $this->state(fn () => [
            'type' => ViewType::TextInput,
            'config' => ['label' => $label ?? fake()->sentence()],
        ]);
    }

    public function html(string $content): static
    {
        return $this->state(fn () => [
            'type' => ViewType::Html,
            'config' => ['content' => $content],
        ]);
    }

    public function childOf(View $parent): static
    {
        return $this->state(fn () => [
            'configurator_id' => $parent->configurator_id,
            'parent_id' => $parent->id,
        ]);
    }
}
