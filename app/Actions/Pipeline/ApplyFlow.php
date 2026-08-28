<?php

namespace App\Actions\Pipeline;

use App\Actions\DTO\ConfigurationEvaluation;
use App\Enums\RuleComponent;
use App\Enums\ViewType;
use App\Models\Rule;
use App\Models\View;
use Closure;
use Illuminate\Support\Collection;

final class ApplyFlow
{
    private const FLOW_RULE_CHUNK_SIZE = 25;

    private const GROUP_ONLY_RELATIONS = ['descendants'];

    public function __construct(
        private readonly EvaluateRule $evaluateRule,
    ) {}

    public function __invoke(ConfigurationEvaluation $evaluation, Closure $next): mixed
    {
        $depth = $evaluation->configuration->configurator->scope_depth;
        $answeredViewIds = $evaluation->configuration->relevantAnswers->pluck('view_id')
            ->merge($evaluation->configuration->relevantAnswerChunks->pluck('anchor_view_id'));
        $rootView = $evaluation->configuration->configurator->rootView;

        $views = $rootView->descendantsAndSelf()->depthFirst()->get();
        $viewsById = $views->keyBy('id');

        $nextView = $evaluation->anchorViewId !== null ? $viewsById->get($evaluation->anchorViewId) : null;

        $lastAnsweredView = $views->reverse()->first(fn (View $view) => $answeredViewIds->contains($view->id));
        $lastAnsweredAtDepth = $lastAnsweredView?->ancestorsAndSelf()->whereDepth($depth - (int) $lastAnsweredView->depth)->first();
        $lastAnsweredIndex = $lastAnsweredAtDepth !== null ? $views->search(fn (View $view) => $view->id === $lastAnsweredAtDepth->id) : 0;

        if (! $nextView) {
            $nextView = $this->findFlowTarget($evaluation, $views, $viewsById, $lastAnsweredIndex, $depth);
        }

        if (! $nextView) {
            $nextView = $lastAnsweredAtDepth !== null
                ? $rootView->descendantsAndSelf()
                    ->whereDepth($depth)
                    ->where('order', $lastAnsweredAtDepth->order + 1)
                    ->first() ?? $lastAnsweredAtDepth
                : $rootView->descendantsAndSelf()->whereDepth($depth)->orderBy('order')->first();
        }

        if (! $nextView) {
            $nextView = $rootView;
        }

        $nextView->loadMissing([
            'bloodline' => fn ($q) => $q->depthFirst(),
            'bloodline.rules',
            'bloodline.templates',
            'bloodline.rules.templates',
        ]);

        $evaluation->anchorViewId = $nextView->id;
        $evaluation->componentContexts['scope'] = $nextView->bloodline->toTree();

        $pendingViewPaths = collect($evaluation->pendingAnswers)->pluck('viewId');
        $pendingViewPaths = $nextView->bloodline->whereIn('id', $pendingViewPaths)->map->path->values()->all();

        $evaluation->componentContexts['pending_views'] = $nextView->bloodline
            ->filter(fn (View $view) => str(implode('|', $pendingViewPaths))->contains($view->id));

        return $next($evaluation);
    }

    /**
     * @param  Collection<int, View>  $views
     * @param  Collection<string, View>  $viewsById
     */
    private function findFlowTarget(ConfigurationEvaluation $evaluation, Collection $views, Collection $viewsById, int $lastAnsweredIndex, int $depth): ?View
    {
        $candidates = $views->slice($lastAnsweredIndex)
            ->filter(fn (View $view) => (int) $view->depth <= $depth)
            ->reverse()
            ->values();

        if ($candidates->isEmpty()) {
            return null;
        }

        $functions = config('templating.functions.'.View::class, []);
        $functionNames = array_keys($functions);
        $with = collect($functions)->flatMap(fn (array $definition): array => $definition['with'])->unique()->values()->all();
        $commonWith = array_values(array_diff($with, self::GROUP_ONLY_RELATIONS));

        foreach ($candidates->chunk(self::FLOW_RULE_CHUNK_SIZE) as $chunk) {
            $rulesByViewId = Rule::query()
                ->where('component', RuleComponent::Flow)
                ->whereIn('view_id', $chunk->pluck('id'))
                ->get()
                ->groupBy('view_id');

            foreach ($chunk as $view) {
                $flowRule = $rulesByViewId->get($view->id, collect())->first();

                if ($flowRule === null) {
                    continue;
                }

                $this->loadReferencedRelations($viewsById, $flowRule, $functionNames, $commonWith);

                $flowContext = [
                    'modelsById' => [View::class => $viewsById->all()],
                    'relevantAnswers' => $evaluation->configuration->relevantAnswers,
                    'relevantAnswerChunks' => $evaluation->configuration->relevantAnswerChunks,
                    '__persistedOnly' => true,
                ];

                if (($this->evaluateRule)($flowRule, $flowContext)) {
                    return $viewsById->get($flowRule->meta['view_id']);
                }
            }
        }

        return null;
    }

    /**
     * @param  Collection<string, View>  $viewsById
     * @param  list<string>  $functionNames
     * @param  list<string>  $commonWith
     */
    private function loadReferencedRelations(Collection $viewsById, Rule $flowRule, array $functionNames, array $commonWith): void
    {
        if ($functionNames === []) {
            return;
        }

        $text = implode(' ', [$flowRule->expression, $flowRule->meta['message'] ?? '']);

        preg_match_all('/\b('.implode('|', $functionNames).')\(\s*[\'"]([^\'"]+)[\'"]\s*\)/', $text, $matches);
        $ids = $matches[2];

        if (preg_match('/\b('.implode('|', $functionNames).')\(\s*this\s*\)/', $text) === 1) {
            $ids[] = $flowRule->view_id;
        }

        $referenced = $viewsById->only(array_unique($ids));

        if ($referenced->isEmpty()) {
            return;
        }

        if ($commonWith !== []) {
            $referenced->loadMissing($commonWith);
        }

        $referenced->filter(fn (View $view) => $view->type === ViewType::Group)->loadMissing(self::GROUP_ONLY_RELATIONS);
    }
}
