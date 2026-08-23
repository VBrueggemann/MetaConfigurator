<?php

namespace App\Livewire;

use App\Actions\GetCurrentConfiguration;
use App\Actions\Rules\CommitConfigurationAnswers;
use App\Actions\Rules\FetchConfigurationState;
use App\Enums\ViewType;
use App\Models\Configuration;
use App\Models\Configurator;
use App\Models\View as ViewModel;
use Illuminate\Contracts\View\View as ViewContract;
use Livewire\Component;

class PlayConfigurator extends Component
{
    public Configurator $configurator;

    public Configuration $configuration;

    /** @var array<string, mixed> */
    public array $values = [];

    /** @var list<string> */
    public array $visibleViewIds = [];

    /** @var list<string> */
    public array $scopeViewIds = [];

    /** @var array<string, list<string>> */
    public array $fieldErrors = [];

    public function mount(Configurator $configurator, GetCurrentConfiguration $getCurrentConfiguration): void
    {
        $this->configurator = $configurator;
        $this->configuration = $getCurrentConfiguration($configurator);

        $this->fetch();
        $this->loadValues();
    }

    public function updatedValues(): void
    {
        $this->fetch();
        $this->loadValues();
    }

    public function commit(): void
    {
        $stepIds = ViewModel::query()
            ->whereIn('id', $this->scopeViewIds)
            ->where('type', ViewType::Group)
            ->pluck('id');

        $pending = array_filter($this->values, fn (mixed $value): bool => $value !== null);
        foreach ($stepIds as $stepId) {
            $pending[$stepId] = true;
        }

        $evaluation = app(CommitConfigurationAnswers::class)($this->configuration, $pending);

        $this->fieldErrors = $evaluation->componentContexts['validation'] ?? [];

        if ($this->fieldErrors !== []) {
            return;
        }

        $this->values = [];
        $this->fetch();
        $this->loadValues();
    }

    /**
     * @return array<int, ViewModel>
     */
    public function items(): array
    {
        return ViewModel::query()
            ->where('configurator_id', $this->configurator->id)
            ->tree()
            ->depthFirst()
            ->get()
            ->filter(fn (ViewModel $view): bool => in_array($view->id, $this->scopeViewIds, true) && $view->type !== ViewType::Group)
            ->values()
            ->all();
    }

    private function fetch(): void
    {
        $evaluation = app(FetchConfigurationState::class)($this->configuration, $this->values);

        $this->visibleViewIds = array_keys(array_filter($evaluation->componentContexts['visibility'] ?? []));
        $this->scopeViewIds = $evaluation->componentContexts['scope'] ?? [];
        $this->fieldErrors = $evaluation->componentContexts['validation'] ?? [];
    }

    private function loadValues(): void
    {
        $items = $this->items();

        $existing = $this->configuration->answers()
            ->whereIn('view_id', array_map(fn (ViewModel $item) => $item->id, $items))
            ->get()
            ->pluck('value', 'view_id');

        foreach ($items as $item) {
            if (array_key_exists($item->id, $this->values)) {
                continue;
            }

            $multiple = $item->config['multiple'] ?? false;
            $this->values[$item->id] = $existing[$item->id] ?? ($multiple ? [] : null);
        }
    }

    public function render(): ViewContract
    {
        return view('livewire.play-configurator', [
            'items' => $this->items(),
        ]);
    }
}
