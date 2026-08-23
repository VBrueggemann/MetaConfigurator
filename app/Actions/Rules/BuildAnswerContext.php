<?php

namespace App\Actions\Rules;

use App\Actions\DTO\ConfigurationEvaluation;
use App\Actions\DTO\PendingAnswer;
use App\Actions\Rules\Scope\ScopeAllStrategy;
use App\Actions\Rules\Scope\ScopeCurrentViewStrategy;
use App\Actions\Rules\Scope\ScopeUntilCurrentViewStrategy;
use App\Enums\ComponentType;
use App\Enums\ScopeStrategy;
use App\Models\Component;
use App\Models\Configuration;
use Closure;
use Staudenmeir\LaravelAdjacencyList\Eloquent\Relations\Descendants;

final class BuildAnswerContext
{
    public function __invoke(ConfigurationEvaluation $evaluation, Closure $next): mixed
    {
        $configuration = $evaluation->configuration;
        $configuration->loadMissing(['answers', 'configurator.rootView']);

        $persistedAnswersByViewId = $configuration->answers->pluck('value', 'view_id');
        $pendingAnswersByViewId = collect($evaluation->pendingAnswers)
            ->mapWithKeys(fn (PendingAnswer $pendingAnswer): array => [$pendingAnswer->viewId => $pendingAnswer->value]);

        $evaluation->context['answers'] = $persistedAnswersByViewId->merge($pendingAnswersByViewId)->all();

        $scopedViews = $configuration->configurator->rootView === null
            ? collect()
            : $this->scopedViews($configuration)->with(['rules'])->depthFirst()->get();

        $evaluation->context['views'] = $scopedViews->pluck('id');

        $evaluation->componentContexts[ComponentType::Scope->value] = $scopedViews;

        return $next($evaluation);
    }

    private function scopedViews(Configuration $configuration): Descendants
    {
        $component = Component::query()
            ->where('configurator_id', $configuration->configurator_id)
            ->where('type', ComponentType::Scope)
            ->first();

        $depth = $component?->config['depth'] ?? 0;
        $strategy = ScopeStrategy::from($component?->config['strategy'] ?? ScopeStrategy::JustNext->value);

        return match ($strategy) {
            ScopeStrategy::All => app(ScopeAllStrategy::class)($configuration),
            ScopeStrategy::JustNext => app(ScopeCurrentViewStrategy::class)($configuration, $depth),
            ScopeStrategy::AnsweredPlusN => app(ScopeUntilCurrentViewStrategy::class)($configuration, $depth),
        };
    }
}
