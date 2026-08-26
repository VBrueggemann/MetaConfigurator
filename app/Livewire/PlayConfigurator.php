<?php

namespace App\Livewire;

use App\Actions\CommitConfigurationAnswers;
use App\Actions\DTO\PendingAnswer;
use App\Actions\FetchConfigurationState;
use App\Actions\GetCurrentConfiguration;
use App\Enums\ViewType;
use App\Models\Answer;
use App\Models\Configuration;
use App\Models\Configurator;
use App\Models\View as ViewModel;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Livewire\Component;

class PlayConfigurator extends Component
{
    public Configurator $configurator;

    public Configuration $configuration;

    /** @var array<string, mixed> */
    public array $values = [];

    /** @var array<string, list<string>> */
    public array $groupSelections = [];

    /** @var list<string> */
    public array $visibleViewIds = [];

    /** @var list<string> */
    public array $scopeViewIds = [];

    /** @var list<string> */
    public array $breadcrumbViewIds = [];

    /** @var array<string, list<string>> */
    public array $fieldErrors = [];

    public ?string $requestedAnchorViewId = null;

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

    public function updatedGroupSelections(mixed $selectedChildIds, string $groupId): void
    {
        $groupId = explode('.', $groupId)[0];
        $selectedChildIds = Arr::wrap($this->groupSelections[$groupId] ?? []);

        $group = ViewModel::query()->find($groupId);

        foreach ($group->children as $child) {
            $this->values[$child->id] = in_array($child->id, $selectedChildIds, true);
        }

        $this->fetch(advanceScope: false);
        $this->loadValues();
    }

    public function goToView(string $viewId): void
    {
        $this->requestedAnchorViewId = $viewId;
        $this->values = [];
        $this->groupSelections = [];

        $this->fetch(advanceScope: true);
        $this->loadValues();
    }

    public function back(): void
    {
        $currentSequence = $this->requestedAnchorViewId !== null
            ? $this->configuration->relevantAnswerChunks()->where('anchor_view_id', $this->requestedAnchorViewId)->value('sequence')
            : null;

        $previousChunk = $this->configuration->relevantAnswerChunks()
            ->when($currentSequence !== null, fn ($query) => $query->where('sequence', '<', $currentSequence))
            ->reorder('sequence', 'desc')
            ->first();

        if ($previousChunk === null) {
            return;
        }

        $this->goToView($previousChunk->anchor_view_id);
    }

    public function next(): void
    {
        $evaluation = app(CommitConfigurationAnswers::class)($this->configuration, $this->toPendingAnswers($this->values), $this->requestedAnchorViewId);

        $this->fieldErrors = $evaluation->componentContexts['validation'] ?? [];

        if ($this->fieldErrors !== []) {
            return;
        }

        $this->requestedAnchorViewId = null;
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
        $evaluation = app(FetchConfigurationState::class)($this->configuration, $this->toPendingAnswers($this->values), $this->requestedAnchorViewId);

        $this->visibleViewIds = array_keys(array_filter($evaluation->componentContexts['visibility'] ?? []));
        $this->fieldErrors = $evaluation->componentContexts['validation'] ?? [];
        $this->breadcrumbViewIds = $evaluation->componentContexts['breadcrumbs']->pluck('id')->all();

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
            ->where('type', ViewType::Input)
            ->get();

        $existing = $this->configuration->answers()
            ->whereIn('view_id', $fields->pluck('id'))
            ->with('answerChunk')
            ->get()
            ->sortByDesc(fn (Answer $answer): int => $answer->answerChunk->sequence)
            ->unique('view_id')
            ->pluck('value', 'view_id');

        foreach ($fields as $field) {
            if (array_key_exists($field->id, $this->values)) {
                continue;
            }

            $this->values[$field->id] = $existing[$field->id] ?? ($field->config['default'] ?? null);
        }

        $this->loadGroupSelections();
    }

    private function loadGroupSelections(): void
    {
        $multiselectGroups = ViewModel::query()
            ->whereIn('id', array_intersect($this->scopeViewIds, $this->visibleViewIds))
            ->where('type', ViewType::Group)
            ->get()
            ->filter(fn (ViewModel $group): bool => ($group->config['widget'] ?? null) === 'multiselect');

        foreach ($multiselectGroups as $group) {
            if (array_key_exists($group->id, $this->groupSelections)) {
                continue;
            }

            $this->groupSelections[$group->id] = $group->children()
                ->get()
                ->filter(fn (ViewModel $child): bool => (bool) ($this->values[$child->id] ?? false))
                ->pluck('id')
                ->values()
                ->all();
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

    /**
     * @return Collection<int, ViewModel>
     */
    public function breadcrumbs(): Collection
    {
        $views = ViewModel::query()->whereIn('id', $this->breadcrumbViewIds)->get()->keyBy('id');

        return collect($this->breadcrumbViewIds)->map(fn (string $viewId) => $views->get($viewId))->filter()->values();
    }

    public function render(): ViewContract
    {
        return view('livewire.play-configurator', [
            'items' => $this->items(),
            'breadcrumbs' => $this->breadcrumbs(),
        ]);
    }
}
