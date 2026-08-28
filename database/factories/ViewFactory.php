<?php

namespace Database\Factories;

use App\Enums\ViewType;
use App\Models\Configurator;
use App\Models\View;
use Closure;
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

    public function group(?string $label = null, ?string $direction = null): static
    {
        $factory = $this->state(fn () => [
            'type' => ViewType::Group,
            'config' => array_filter(['direction' => $direction], fn ($value) => $value !== null),
        ]);

        return $label !== null ? $factory->afterCreating($this->attachLabel($label)) : $factory;
    }

    public function select(array $options, ?string $label = null): static
    {
        return $this->state(fn () => [
            'type' => ViewType::Input,
            'config' => ['widget' => 'select', 'options' => $this->stripOptionLabels($options)],
        ])->afterCreating($this->attachLabel($label))->afterCreating($this->attachOptionLabels($options));
    }

    public function textInput(?string $label = null): static
    {
        return $this->state(fn () => [
            'type' => ViewType::Input,
            'config' => ['widget' => 'text'],
        ])->afterCreating($this->attachLabel($label));
    }

    public function checkbox(?string $label = null): static
    {
        return $this->state(fn () => [
            'type' => ViewType::Input,
            'config' => ['widget' => 'checkbox', 'default' => false],
        ])->afterCreating($this->attachLabel($label));
    }

    public function number(?string $label = null, int|float|null $default = null): static
    {
        return $this->state(fn () => [
            'type' => ViewType::Input,
            'config' => array_filter([
                'widget' => 'number',
                'default' => $default,
            ], fn ($value) => $value !== null),
        ])->afterCreating($this->attachLabel($label));
    }

    public function color(?string $label = null): static
    {
        return $this->state(fn () => [
            'type' => ViewType::Input,
            'config' => ['widget' => 'color'],
        ])->afterCreating($this->attachLabel($label));
    }

    public function date(?string $label = null): static
    {
        return $this->state(fn () => [
            'type' => ViewType::Input,
            'config' => ['widget' => 'date'],
        ])->afterCreating($this->attachLabel($label));
    }

    public function email(?string $label = null): static
    {
        return $this->state(fn () => [
            'type' => ViewType::Input,
            'config' => ['widget' => 'email'],
        ])->afterCreating($this->attachLabel($label));
    }

    public function range(?string $label = null, ?int $min = null, ?int $max = null): static
    {
        return $this->state(fn () => [
            'type' => ViewType::Input,
            'config' => array_filter([
                'widget' => 'range',
                'min' => $min,
                'max' => $max,
            ], fn ($value) => $value !== null),
        ])->afterCreating($this->attachLabel($label));
    }

    public function radio(array $options, ?string $label = null): static
    {
        return $this->state(fn () => [
            'type' => ViewType::Input,
            'config' => ['widget' => 'radio', 'options' => $this->stripOptionLabels($options)],
        ])->afterCreating($this->attachLabel($label))->afterCreating($this->attachOptionLabels($options));
    }

    public function datalist(array $options, ?string $label = null): static
    {
        return $this->state(fn () => [
            'type' => ViewType::Input,
            'config' => ['widget' => 'datalist', 'options' => $this->stripOptionLabels($options)],
        ])->afterCreating($this->attachLabel($label))->afterCreating($this->attachOptionLabels($options));
    }

    public function multiselectGroup(?string $label = null): static
    {
        return $this->state(fn () => [
            'type' => ViewType::Group,
            'config' => ['widget' => 'multiselect'],
        ])->afterCreating($this->attachLabel($label));
    }

    public function html(string $content): static
    {
        return $this->state(fn () => [
            'type' => ViewType::Display,
            'config' => [],
        ])->afterCreating($this->attachContent($content));
    }

    public function stat(string $label, string $content): static
    {
        return $this->state(fn () => [
            'type' => ViewType::Display,
            'config' => ['widget' => 'stat'],
        ])->afterCreating($this->attachLabel($label))->afterCreating($this->attachContent($content));
    }

    public function cardToggle(string $title, ?string $icon = null, ?string $subtitle = null): static
    {
        return $this->state(fn () => [
            'type' => ViewType::Input,
            'config' => array_filter([
                'widget' => 'card-toggle',
                'title' => $title,
                'icon' => $icon,
                'subtitle' => $subtitle,
                'default' => false,
            ], fn ($value) => $value !== null),
        ]);
    }

    public function cardRadio(array $options, ?string $label = null): static
    {
        return $this->state(fn () => [
            'type' => ViewType::Input,
            'config' => ['widget' => 'card-radio', 'options' => $this->stripOptionLabels($options)],
        ])->afterCreating($this->attachLabel($label))->afterCreating($this->attachOptionLabels($options));
    }

    public function updateOn(string $updateOn): static
    {
        return $this->state(fn (array $attributes) => [
            'config' => array_merge($attributes['config'] ?? [], ['updateOn' => $updateOn]),
        ]);
    }

    public function childOf(View $parent): static
    {
        return $this->state(fn () => [
            'configurator_id' => $parent->configurator_id,
            'parent_id' => $parent->id,
        ]);
    }

    private function attachLabel(?string $label): Closure
    {
        return fn (View $view) => $view->templates()->create(['key' => 'label', 'template' => $label ?? fake()->sentence()]);
    }

    private function attachContent(string $content): Closure
    {
        return fn (View $view) => $view->templates()->create(['key' => 'content', 'template' => $content]);
    }

    /**
     * @param  list<array<string, mixed>>  $options
     */
    private function attachOptionLabels(array $options): Closure
    {
        return function (View $view) use ($options): void {
            foreach (array_values($options) as $index => $option) {
                if (isset($option['label'])) {
                    $view->templates()->create(['key' => 'option_label', 'template' => $option['label'], 'order' => $index]);
                }
            }
        };
    }

    /**
     * @param  list<array<string, mixed>>  $options
     * @return list<array<string, mixed>>
     */
    private function stripOptionLabels(array $options): array
    {
        return array_map(fn (array $option) => collect($option)->except('label')->all(), $options);
    }
}
