<?php

namespace App\Actions\Rules;

use App\Actions\DTO\ConfigurationEvaluation;
use App\Enums\ComponentType;
use App\Enums\RuleComponent;
use App\Models\Component;
use App\Models\View;
use Closure;
use Illuminate\Support\Collection;

final class ApplyFlow
{
    public function __construct(
        private readonly EvaluateRule $evaluateRule,
    ) {}

    public function __invoke(ConfigurationEvaluation $evaluation, Closure $next): mixed
    {
        $views = $evaluation->componentContexts[ComponentType::Views->value] ?? new Collection;

        if ($views->isEmpty()) {
            return $next($evaluation);
        }

        $component = Component::query()
            ->where('configurator_id', $evaluation->configuration->configurator_id)
            ->where('type', ComponentType::Scope)
            ->first();

        $depth = $component?->config['depth'] ?? 0;
        $answeredViewIds = $evaluation->configuration->answers->pluck('view_id');

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
                ? $evaluation->configuration->configurator->rootView->descendantsAndSelf()
                    ->whereDepth($depth)
                    ->where('order', $lastAnsweredAtDepth->order + 1)
                    ->first() ?? $lastAnsweredAtDepth
                : $evaluation->configuration->configurator->rootView->descendantsAndSelf()->whereDepth($depth)->orderBy('order')->first();
        }

        if ($nextView === null) {
            return $next($evaluation);
        }

        $nextView->loadMissing(['bloodline.rules']);

        $evaluation->componentContexts[ComponentType::Scope->value] = $nextView->bloodline;

        $evaluation->componentContexts[ComponentType::PendingViews->value] = collect($evaluation->pendingAnswers)
            ->filter(fn ($pendingAnswer) => $nextView->bloodline->contains('id', $pendingAnswer->viewId))
            ->values();

        return $next($evaluation);
    }
}
