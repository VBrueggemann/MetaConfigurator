<?php

namespace App\Livewire;

use App\Actions\Rules\EvaluateGroup;
use App\Actions\Rules\ResolveCurrentGroup;
use App\Models\Answer;
use App\Models\Configuration;
use App\Models\Configurator;
use App\Models\View as ViewModel;
use Illuminate\Contracts\View\View as ViewContract;
use Livewire\Component;

class PlayConfigurator extends Component
{
    public Configurator $configurator;

    public Configuration $configuration;

    public ViewModel $currentGroup;

    /** @var array<string, mixed> */
    public array $values = [];

    /** @var list<string> */
    public array $visibleViewIds = [];

    /** @var array<string, list<string>> */
    public array $fieldErrors = [];

    public ?string $nextGroupId = null;

    public function mount(Configurator $configurator, ResolveCurrentGroup $resolveCurrentGroup): void
    {
        $this->configurator = $configurator;

        $sessionKey = "metaconfigurator.configuration.{$configurator->id}";
        $configuration = null;

        if ($configurationId = session($sessionKey)) {
            $configuration = Configuration::query()->where('id', $configurationId)->first();
        }

        if (! $configuration) {
            $configuration = Configuration::create(['configurator_id' => $configurator->id]);
            session([$sessionKey => $configuration->id]);
        }

        $this->configuration = $configuration;
        $this->currentGroup = $resolveCurrentGroup($configuration);

        $this->loadCurrentGroup();
    }

    public function updatedValues(mixed $value, string $key): void
    {
        Answer::updateOrCreate(
            ['configuration_id' => $this->configuration->id, 'view_id' => $key],
            ['value' => $value],
        );

        $this->evaluate();
    }

    public function next(): void
    {
        Answer::updateOrCreate(
            ['configuration_id' => $this->configuration->id, 'view_id' => $this->currentGroup->id],
            ['value' => true],
        );

        $this->evaluate();

        if (! empty($this->fieldErrors) || $this->nextGroupId === null) {
            return;
        }

        $this->currentGroup = ViewModel::query()->where('id', $this->nextGroupId)->firstOrFail();
        $this->loadCurrentGroup();
    }

    /**
     * @return array<int, ViewModel>
     */
    public function items(): array
    {
        return ViewModel::query()->where('parent_id', $this->currentGroup->id)->orderBy('order')->get()->all();
    }

    private function loadCurrentGroup(): void
    {
        $items = $this->items();
        $existing = $this->configuration->answers()
            ->whereIn('view_id', array_map(fn (ViewModel $item) => $item->id, $items))
            ->get()
            ->pluck('value', 'view_id');

        $this->values = [];
        foreach ($items as $item) {
            $multiple = $item->config['multiple'] ?? false;
            $this->values[$item->id] = $existing[$item->id] ?? ($multiple ? [] : null);
        }

        $this->evaluate();
    }

    private function evaluate(): void
    {
        $evaluation = app(EvaluateGroup::class)($this->currentGroup, $this->configuration);

        $this->visibleViewIds = $evaluation->componentContexts['visibility'] ?? [];
        $this->fieldErrors = $evaluation->componentContexts['validation'] ?? [];
        $this->nextGroupId = $evaluation->componentContexts['next_group'] ?? null;
    }

    public function render(): ViewContract
    {
        return view('livewire.play-configurator', [
            'items' => $this->items(),
        ]);
    }
}
