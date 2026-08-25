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

    public function group(?string $label = null): static
    {
        return $this->state(fn () => [
            'type' => ViewType::Group,
            'config' => $label === null ? [] : ['label' => $label],
        ]);
    }

    public function select(array $options, ?string $label = null): static
    {
        return $this->state(fn () => [
            'type' => ViewType::Input,
            'config' => ['label' => $label ?? fake()->sentence(), 'widget' => 'select', 'options' => $options],
        ]);
    }

    public function textInput(?string $label = null): static
    {
        return $this->state(fn () => [
            'type' => ViewType::Input,
            'config' => ['label' => $label ?? fake()->sentence(), 'widget' => 'text'],
        ]);
    }

    public function checkbox(?string $label = null): static
    {
        return $this->state(fn () => [
            'type' => ViewType::Input,
            'config' => ['label' => $label ?? fake()->sentence(), 'widget' => 'checkbox', 'default' => false],
        ]);
    }

    public function number(?string $label = null, int|float|null $default = null): static
    {
        return $this->state(fn () => [
            'type' => ViewType::Input,
            'config' => array_filter([
                'label' => $label ?? fake()->sentence(),
                'widget' => 'number',
                'default' => $default,
            ], fn ($value) => $value !== null),
        ]);
    }

    public function color(?string $label = null): static
    {
        return $this->state(fn () => [
            'type' => ViewType::Input,
            'config' => ['label' => $label ?? fake()->sentence(), 'widget' => 'color'],
        ]);
    }

    public function date(?string $label = null): static
    {
        return $this->state(fn () => [
            'type' => ViewType::Input,
            'config' => ['label' => $label ?? fake()->sentence(), 'widget' => 'date'],
        ]);
    }

    public function email(?string $label = null): static
    {
        return $this->state(fn () => [
            'type' => ViewType::Input,
            'config' => ['label' => $label ?? fake()->sentence(), 'widget' => 'email'],
        ]);
    }

    public function range(?string $label = null, ?int $min = null, ?int $max = null): static
    {
        return $this->state(fn () => [
            'type' => ViewType::Input,
            'config' => array_filter([
                'label' => $label ?? fake()->sentence(),
                'widget' => 'range',
                'min' => $min,
                'max' => $max,
            ], fn ($value) => $value !== null),
        ]);
    }

    public function radio(array $options, ?string $label = null): static
    {
        return $this->state(fn () => [
            'type' => ViewType::Input,
            'config' => ['label' => $label ?? fake()->sentence(), 'widget' => 'radio', 'options' => $options],
        ]);
    }

    public function datalist(array $options, ?string $label = null): static
    {
        return $this->state(fn () => [
            'type' => ViewType::Input,
            'config' => ['label' => $label ?? fake()->sentence(), 'widget' => 'datalist', 'options' => $options],
        ]);
    }

    public function multiselectGroup(?string $label = null): static
    {
        return $this->state(fn () => [
            'type' => ViewType::Group,
            'config' => ['label' => $label ?? fake()->sentence(), 'widget' => 'multiselect'],
        ]);
    }

    public function html(string $content): static
    {
        return $this->state(fn () => [
            'type' => ViewType::Display,
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
