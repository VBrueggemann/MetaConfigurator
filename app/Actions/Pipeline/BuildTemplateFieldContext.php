<?php

namespace App\Actions\Pipeline;

use App\Actions\DTO\ConfigurationEvaluation;
use App\Models\Rule;
use App\Models\View;
use Closure;
use Illuminate\Support\Collection;

final class BuildTemplateFieldContext
{
    public function __invoke(ConfigurationEvaluation $evaluation, Closure $next): mixed
    {
        $configuratorId = $evaluation->configuration->configurator_id;

        $views = View::query()->where('configurator_id', $configuratorId)->get();
        $rules = Rule::query()->whereIn('view_id', $views->pluck('id'))->get();

        $registry = config('templating.fields');

        $evaluation->context['templateFields'] = [
            'views' => $this->fieldsByModel($views, $registry[View::class] ?? []),
            'rules' => $this->fieldsByModel($rules, $registry[Rule::class] ?? []),
        ];

        return $next($evaluation);
    }

    /**
     * @param  Collection<int, View|Rule>  $models
     * @param  array<string, Closure>  $fields
     * @return array<string, array<string, mixed>>
     */
    private function fieldsByModel(Collection $models, array $fields): array
    {
        return $models
            ->mapWithKeys(fn (View|Rule $model): array => [
                $model->id => collect($fields)->map(fn (Closure $accessor) => $accessor($model))->all(),
            ])
            ->all();
    }
}
