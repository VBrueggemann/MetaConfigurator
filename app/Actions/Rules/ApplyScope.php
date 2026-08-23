<?php

namespace App\Actions\Rules;

use App\Actions\DTO\ConfigurationEvaluation;
use App\Enums\ComponentType;
use App\Enums\ScopeStrategy;
use App\Models\Component;
use App\Models\View;
use Closure;
use Illuminate\Support\Collection;

final class ApplyScope
{
    public function __construct(
        private readonly EvaluateViewValidity $evaluateViewValidity,
    ) {}

    public function __invoke(ConfigurationEvaluation $evaluation, Closure $next): mixed
    {
        $component = Component::query()
            ->where('configurator_id', $evaluation->configuration->configurator_id)
            ->where('type', ComponentType::Scope)
            ->first();

        $depth = $component?->config['depth'] ?? 0;
        $strategy = ScopeStrategy::from($component?->config['strategy'] ?? ScopeStrategy::JustNext->value);
        $aheadCount = $component?->config['n'] ?? 1;

        $views = View::query()
            ->where('configurator_id', $evaluation->configuration->configurator_id)
            ->tree()
            ->depthFirst()
            ->get();

        [$steps, $descendantIdsByStepId] = $this->groupByStep($views, $depth);

        /** @var array<string, bool> $visibility */
        $visibility = $evaluation->componentContexts[ComponentType::Visibility->value] ?? [];

        $visibleSteps = $steps->filter(fn (View $step) => $visibility[$step->id] ?? false)->values();

        /** @var list<string> $answeredViewIds */
        $answeredViewIds = $evaluation->context['answeredViewIds'] ?? [];

        $completedCount = 0;
        foreach ($visibleSteps as $step) {
            if (! $this->isStepComplete($step, $descendantIdsByStepId, $visibility, $answeredViewIds, $evaluation->context)) {
                break;
            }

            $completedCount++;
        }

        $revealedSteps = match ($strategy) {
            ScopeStrategy::All => $visibleSteps,
            ScopeStrategy::JustNext => $visibleSteps->slice(min($completedCount, max($visibleSteps->count() - 1, 0)), 1),
            ScopeStrategy::AnsweredPlusN => $visibleSteps->slice(0, min($completedCount + $aheadCount, $visibleSteps->count())),
        };

        $revealedViewIds = [];

        foreach ($revealedSteps as $step) {
            $revealedViewIds[] = $step->id;
            array_push($revealedViewIds, ...($descendantIdsByStepId[$step->id] ?? []));
        }

        $evaluation->componentContexts[ComponentType::Scope->value] = $revealedViewIds;

        return $next($evaluation);
    }

    /**
     * @param  Collection<int, View>  $views
     * @return array{0: Collection<int, View>, 1: array<string, list<string>>}
     */
    private function groupByStep(Collection $views, int $depth): array
    {
        $steps = collect();
        $descendantIdsByStepId = [];
        $currentStepId = null;

        foreach ($views as $view) {
            if ($view->depth < $depth) {
                $currentStepId = null;

                continue;
            }

            if ($view->depth === $depth) {
                $currentStepId = $view->id;
                $steps->push($view);

                continue;
            }

            if ($currentStepId !== null) {
                $descendantIdsByStepId[$currentStepId][] = $view->id;
            }
        }

        return [$steps, $descendantIdsByStepId];
    }

    /**
     * @param  array<string, list<string>>  $descendantIdsByStepId
     * @param  array<string, bool>  $visibility
     * @param  list<string>  $answeredViewIds
     * @param  array<string, mixed>  $context
     */
    private function isStepComplete(View $step, array $descendantIdsByStepId, array $visibility, array $answeredViewIds, array $context): bool
    {
        if (! in_array($step->id, $answeredViewIds, true)) {
            return false;
        }

        $relevantIds = [$step->id, ...($descendantIdsByStepId[$step->id] ?? [])];

        foreach ($relevantIds as $viewId) {
            if ($viewId !== $step->id && ! ($visibility[$viewId] ?? false)) {
                continue;
            }

            if (($this->evaluateViewValidity)($viewId, $context) !== []) {
                return false;
            }
        }

        return true;
    }
}
