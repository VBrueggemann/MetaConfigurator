<?php

namespace App\Actions\Rules;

use App\Actions\DTO\ConfigurationEvaluation;
use App\Enums\ComponentType;
use App\Enums\RuleComponent;
use App\Models\Component;
use App\Models\View;
use Closure;

final class ApplyFlow
{
    public function __construct(
        private readonly EvaluateRule $evaluateRule,
    ) {}

    public function __invoke(ConfigurationEvaluation $evaluation, Closure $next): mixed
    {
        $component = Component::query()
            ->where('configurator_id', $evaluation->configuration->configurator_id)
            ->where('type', ComponentType::Scope)
            ->first();

        $depth = $component?->config['depth'] ?? 0;
        $answeredViewIds = $evaluation->configuration->answers->pluck('view_id');
        $rootView = $evaluation->configuration->configurator->rootView;

        $views = $rootView->descendantsAndSelf()->with('rules')->depthFirst()
            ->whereHas('descendantsAndSelf', fn ($q) => $q->whereIn('id', $answeredViewIds))
            ->get();

        $nextView = null;

        foreach ($views->reverse() as $view) {
            if ((int) $view->depth > $depth) {
                continue;
            }

            $flowRule = $view->rules->first(fn ($rule) => $rule->component === RuleComponent::Flow);

            if ($flowRule !== null && ($this->evaluateRule)($flowRule, $evaluation->context)) {
                $nextView = $views->find($flowRule->meta['view_id']);

                break;
            }
        }

        if (! $nextView) {
            $lastAnsweredView = $views->reverse()->first(fn (View $view) => $answeredViewIds->contains($view->id));
            $lastAnsweredAtDepth = $lastAnsweredView?->ancestorsAndSelf()->whereDepth($depth - (int) $lastAnsweredView->depth)->first();

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

        $nextView->loadMissing(['bloodline' => fn ($q) => $q->depthFirst(), 'bloodline.rules']);

        $evaluation->componentContexts[ComponentType::Scope->value] = $nextView->bloodline->toTree();
        $evaluation->componentContexts[ComponentType::AnsweredViews->value] = $views;

        $pendingViewPaths = collect($evaluation->pendingAnswers)->pluck('viewId');
        $pendingViewPaths = $nextView->bloodline->whereIn('id', $pendingViewPaths)->map->path->values()->all();

        $evaluation->componentContexts[ComponentType::PendingViews->value] = $nextView->bloodline
            ->filter(fn (View $view) => str(implode('|', $pendingViewPaths))->contains($view->id));

        return $next($evaluation);
    }
}
