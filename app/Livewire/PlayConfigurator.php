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
use Illuminate\Support\Collection;
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

        $this->fetch(advanceScope: true);
        $this->loadValues();
    }

    public function updatedValues(): void
    {
        $this->fetch(advanceScope: false);
        $this->loadValues();
    }

    public function next(): void
    {
        $stepIds = ViewModel::query()
            ->whereIn('id', $this->scopeViewIds)
            ->where('type', ViewType::Group)
            ->pluck('id');

        $pending = $this->values;
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
        $this->loadValues();
    }

    /**
     * Rebuilt fresh from scopeViewIds on every render, rather than stored as component state,
     * since a stored Eloquent tree loses its "children" relation across Livewire's
     * dehydrate/hydrate cycle, and re-loading it lazily is disabled.
     *
     * @return Collection<int, ViewModel>
     */
    public function items()
    {
        $views = ViewModel::query()->whereIn('id', $this->scopeViewIds)->orderBy('order')->get()->keyBy('id');

        foreach ($views as $view) {
            $view->setRelation('children', $views->where('parent_id', $view->id)->values());
        }

        return $views->whereNull('parent_id')->values();
    }

    private function fetch(bool $advanceScope): void
    {
        $evaluation = app(FetchConfigurationState::class)($this->configuration, $this->toPendingAnswers($this->values));

        $this->visibleViewIds = array_keys(array_filter($evaluation->componentContexts['visibility'] ?? []));
        $this->fieldErrors = $evaluation->componentContexts['validation'] ?? [];

        if ($advanceScope) {
            $this->scopeViewIds = $this->flattenViewIds($evaluation->componentContexts['scope']);
        }
    }

    /**
     * @param  iterable<ViewModel>  $views
     * @return list<string>
     */
    private function flattenViewIds(iterable $views): array
    {
        return collect($views)->flatMap(fn (ViewModel $view): array => [$view->id, ...$this->flattenViewIds($view->children)])->all();
    }

    private function loadValues(): void
    {
        $fields = ViewModel::query()
            ->whereIn('id', array_intersect($this->scopeViewIds, $this->visibleViewIds))
            ->whereNotIn('type', [ViewType::Group, ViewType::Html])
            ->get();

        $existing = $this->configuration->answers()
            ->whereIn('view_id', $fields->pluck('id'))
            ->get()
            ->pluck('value', 'view_id');

        foreach ($fields as $field) {
            if (array_key_exists($field->id, $this->values)) {
                continue;
            }

            $this->values[$field->id] = $existing[$field->id] ?? ($field->type === ViewType::Checkbox ? false : null);
        }
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
