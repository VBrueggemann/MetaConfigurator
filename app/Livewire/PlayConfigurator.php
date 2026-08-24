<?php

namespace App\Livewire;

use App\Actions\DTO\PendingAnswer;
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

    public $items;

    public function mount(Configurator $configurator, GetCurrentConfiguration $getCurrentConfiguration): void
    {
        $this->configurator = $configurator;
        $this->configuration = $getCurrentConfiguration($configurator);

        $this->fetch(advanceScope: true);
    }

    public function updatedValues(): void
    {
        $this->fetch(advanceScope: false);
    }

    public function next(): void
    {
        $stepIds = ViewModel::query()
            ->whereIn('id', $this->scopeViewIds)
            ->where('type', ViewType::Group)
            ->pluck('id');

        $pending = array_filter($this->values, fn (mixed $value): bool => $value !== null);
        foreach ($stepIds as $stepId) {
            $pending[$stepId] = true;
        }

        $evaluation = app(CommitConfigurationAnswers::class)($this->configuration, $this->toPendingAnswers($pending));

        $this->fieldErrors = $evaluation->componentContexts['validation'] ?? [];

        if ($this->fieldErrors !== []) {
            return;
        }

        $this->values = [];
        $this->fetch(advanceScope: true);
    }

    public function items()
    {
        return $this->items;
    }

    private function fetch(bool $advanceScope): void
    {
        $evaluation = app(FetchConfigurationState::class)($this->configuration, $this->toPendingAnswers($this->values));

        $this->fieldErrors = $evaluation->componentContexts['validation'] ?? [];
        $this->items = $evaluation->componentContexts['scope'];
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<int, PendingAnswer>
     */
    private function toPendingAnswers(array $values): array
    {
        return array_map(
            fn (string $viewId, bool|int|string|null $value): PendingAnswer => new PendingAnswer($viewId, $value),
            array_keys($values),
            array_values($values),
        );
    }

    public function render(): ViewContract
    {
        return view('livewire.play-configurator', [
            'items' => $this->items(),
        ]);
    }
}
