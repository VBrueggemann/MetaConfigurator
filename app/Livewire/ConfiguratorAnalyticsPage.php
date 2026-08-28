<?php

namespace App\Livewire;

use App\Actions\BuildConfiguratorAnalytics;
use App\Actions\DTO\ConfiguratorAnalytics;
use App\Models\Configurator;
use App\Models\View as ViewModel;
use Illuminate\Contracts\View\View as ViewContract;
use Livewire\Component;

class ConfiguratorAnalyticsPage extends Component
{
    public Configurator $configurator;

    private ConfiguratorAnalytics $analytics;

    public function mount(Configurator $configurator, BuildConfiguratorAnalytics $buildConfiguratorAnalytics): void
    {
        $this->configurator = $configurator;
        $this->analytics = $buildConfiguratorAnalytics($configurator);
    }

    public function formatValue(ViewModel $view, mixed $value): string
    {
        $options = $view->config['options'] ?? null;

        if (is_array($options)) {
            $option = collect($options)->firstWhere('value', $value);

            if ($option !== null) {
                return (string) ($option['label'] ?? $value);
            }
        }

        return match (true) {
            $value === null => 'No answer',
            is_bool($value) => $value ? 'Yes' : 'No',
            is_array($value) => implode(', ', $value),
            default => (string) $value,
        };
    }

    public function render(): ViewContract
    {
        if (! isset($this->analytics)) {
            $this->analytics = app(BuildConfiguratorAnalytics::class)($this->configurator);
        }

        return view('livewire.configurator-analytics', ['analytics' => $this->analytics]);
    }
}
