<?php

namespace App\Livewire;

use App\Actions\CommitConfigurationAnswers;
use App\Actions\DTO\PendingAnswer;
use App\Actions\FetchConfigurationState;
use App\Actions\GetCurrentConfiguration;
use App\Enums\RuleComponent;
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

    /** @var array<string, list<string>> */
    public array $groupChildViewIds = [];

    /** @var list<string> */
    public array $scopeViewIds = [];

    /** @var list<string> */
    public array $breadcrumbViewIds = [];

    /** @var array<string, list<string>> */
    public array $fieldErrors = [];

    public ?string $requestedAnchorViewId = null;

    /** @var Collection<string, ViewModel> */
    private Collection $scopeViews;

    /** @var Collection<int, ViewModel> */
    private Collection $breadcrumbViews;

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

        foreach ($this->groupChildViewIds[$groupId] ?? [] as $childId) {
            $this->values[$childId] = in_array($childId, $selectedChildIds, true);
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
        $currentIndex = $this->requestedAnchorViewId !== null
            ? array_search($this->requestedAnchorViewId, $this->breadcrumbViewIds, true)
            : count($this->breadcrumbViewIds);

        if ($currentIndex === false || $currentIndex === 0) {
            return;
        }

        $this->goToView($this->breadcrumbViewIds[$currentIndex - 1]);
    }

    public function next(): void
    {
        $evaluation = app(CommitConfigurationAnswers::class)($this->configuration, $this->toPendingAnswers($this->values), $this->requestedAnchorViewId);

        $this->fieldErrors = $this->deriveFieldErrors($evaluation->componentContexts['scope']);

        if ($this->fieldErrors !== []) {
            $this->refreshRenderState();

            return;
        }

        $this->requestedAnchorViewId = null;
        $this->values = [];
        $this->fetch(advanceScope: true);
        $this->loadValues();
    }

    /**
     * @return Collection<int, ViewModel>
     */
    public function items(): Collection
    {
        return $this->scopeViews->whereNull('parent_id')->values();
    }

    /**
     * @return list<string>
     */
    public function visibleViewIds(): array
    {
        return $this->scopeViews->keys()->all();
    }

    private function fetch(bool $advanceScope): void
    {
        $evaluation = app(FetchConfigurationState::class)($this->configuration, $this->toPendingAnswers($this->values), $this->requestedAnchorViewId);

        $this->fieldErrors = $this->deriveFieldErrors($evaluation->componentContexts['scope']);
        $this->breadcrumbViews = $evaluation->componentContexts['breadcrumbs'];
        $this->breadcrumbViewIds = $this->breadcrumbViews->pluck('id')->all();
        $this->scopeViews = collect($evaluation->componentContexts['scope'])->toList()->keyBy('id');

        if ($advanceScope) {
            $this->scopeViewIds = $this->scopeViews->keys()->all();
        }
    }

    private function refreshRenderState(): void
    {
        $evaluation = app(FetchConfigurationState::class)($this->configuration, $this->toPendingAnswers($this->values), $this->requestedAnchorViewId);

        $this->breadcrumbViews = $evaluation->componentContexts['breadcrumbs'];
        $this->scopeViews = collect($evaluation->componentContexts['scope'])->toList()->keyBy('id');
    }

    /**
     * @param  iterable<ViewModel>  $roots
     * @return array<string, list<string>>
     */
    private function deriveFieldErrors(iterable $roots): array
    {
        return collect($roots)->toList()
            ->mapWithKeys(fn (ViewModel $view): array => [
                $view->id => $view->rules->where('component', RuleComponent::Validation)->pluck('meta.message')->all(),
            ])
            ->filter(fn (array $messages): bool => $messages !== [])
            ->all();
    }

    private function loadValues(): void
    {
        $fields = $this->scopeViews
            ->filter(fn (ViewModel $view): bool => $view->type === ViewType::Input);

        $existing = $this->configuration->answers()
            ->whereIn('view_id', $fields->keys()->all())
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
        $multiselectGroups = $this->scopeViews
            ->filter(fn (ViewModel $view): bool => $view->type === ViewType::Group && ($view->config['widget'] ?? null) === 'multiselect');

        foreach ($multiselectGroups as $group) {
            $childIds = $group->children->pluck('id')->all();
            $this->groupChildViewIds[$group->id] = $childIds;

            if (array_key_exists($group->id, $this->groupSelections)) {
                continue;
            }

            $this->groupSelections[$group->id] = collect($childIds)
                ->filter(fn (string $childId): bool => (bool) ($this->values[$childId] ?? false))
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
        return $this->breadcrumbViews;
    }

    public function render(): ViewContract
    {
        if (! isset($this->scopeViews)) {
            $this->fetch(advanceScope: true);
            $this->loadValues();
        }

        return view('livewire.play-configurator', [
            'items' => $this->items(),
            'breadcrumbs' => $this->breadcrumbs(),
        ]);
    }
}
