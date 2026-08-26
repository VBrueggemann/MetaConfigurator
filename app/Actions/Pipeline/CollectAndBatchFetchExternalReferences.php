<?php

namespace App\Actions\Pipeline;

use App\Actions\DTO\ConfigurationEvaluation;
use App\Enums\ComponentType;
use App\Models\Component;
use App\Models\Rule;
use App\Models\View;
use Closure;

final class CollectAndBatchFetchExternalReferences
{
    public function __invoke(ConfigurationEvaluation $evaluation, Closure $next): mixed
    {
        $scopeViewsById = collect($evaluation->componentContexts[ComponentType::Scope->value] ?? [])->toList()->keyBy('id');

        $functions = config('templating.functions.'.View::class, []);
        $with = collect($functions)->flatMap(fn (array $definition): array => $definition['with'])->unique()->values()->all();

        $formulas = Component::query()
            ->where('configurator_id', $evaluation->configuration->configurator_id)
            ->where('type', ComponentType::Calculation)
            ->first()?->config['formulas'] ?? [];

        $referencedIds = [
            ...$this->scanReferences(array_keys($functions), [
                ...$scopeViewsById->flatMap(fn (View $view): array => $this->templatableStrings($view))->all(),
                ...array_values($formulas),
            ]),
            ...$scopeViewsById->filter(
                fn (View $view): bool => $this->referencesThis(array_keys($functions), $view->rules->pluck('expression')->all())
            )->keys()->all(),
        ];
        $referencedIds = array_values(array_unique($referencedIds));

        $inScopeIds = array_values(array_intersect($referencedIds, $scopeViewsById->keys()->all()));
        $externalIds = array_values(array_diff($referencedIds, $inScopeIds));

        if ($inScopeIds !== []) {
            $scopeViewsById->only($inScopeIds)->loadMissing($with);
        }

        $modelsById = $scopeViewsById->all();

        if ($externalIds !== []) {
            $modelsById += View::query()->whereIn('id', $externalIds)->with($with)->get()->keyBy('id')->all();
        }

        $evaluation->context['modelsById'][View::class] = $modelsById;
        $evaluation->context['calculationFormulas'] = $formulas;
        $evaluation->context['relevantAnswers'] = $evaluation->configuration->relevantAnswers;
        $evaluation->context['relevantAnswerChunks'] = $evaluation->configuration->relevantAnswerChunks;
        $evaluation->context['pendingAnswers'] = $evaluation->pendingAnswers;

        return $next($evaluation);
    }

    /**
     * @return list<string>
     */
    private function templatableStrings(View $view): array
    {
        return [
            $view->config['label'] ?? '',
            $view->config['content'] ?? '',
            ...collect($view->config['options'] ?? [])->pluck('label')->filter()->all(),
            ...$view->rules->flatMap(fn (Rule $rule): array => [$rule->expression, $rule->meta['message'] ?? ''])->all(),
        ];
    }

    /**
     * @param  list<string>  $functionNames
     * @param  list<string>  $strings
     * @return list<string>
     */
    private function scanReferences(array $functionNames, array $strings): array
    {
        if ($functionNames === []) {
            return [];
        }

        $pattern = '/\b('.implode('|', $functionNames).')\(\s*[\'"]([^\'"]+)[\'"]\s*\)/';

        preg_match_all($pattern, implode(' ', $strings), $matches);

        return array_values(array_unique($matches[2]));
    }

    /**
     * @param  list<string>  $functionNames
     * @param  list<string>  $expressions
     */
    private function referencesThis(array $functionNames, array $expressions): bool
    {
        if ($functionNames === []) {
            return false;
        }

        $pattern = '/\b('.implode('|', $functionNames).')\(\s*this\s*\)/';

        return preg_match($pattern, implode(' ', $expressions)) === 1;
    }
}
