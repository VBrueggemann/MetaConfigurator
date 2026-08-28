<?php

namespace App\Actions\Pipeline;

use App\Actions\DTO\ConfigurationEvaluation;
use App\Models\Configurator;
use Closure;
use RuntimeException;

final class BakeConfiguratorGraphs
{
    public function __invoke(ConfigurationEvaluation $evaluation, Closure $next): mixed
    {
        $configurator = $evaluation->configuration->configurator;

        if (! $configurator->graphs_acyclic) {
            $calculationOrder = $this->topologicalOrder(
                $this->calculationDependencies($configurator),
                fn (string $name): string => "Circular calculation dependency involving '{$name}'.",
            );
            $this->topologicalOrder(
                $this->labelReferences($configurator),
                fn (string $viewId): string => "Circular label reference involving view '{$viewId}'.",
            );

            $configurator->update([
                'graphs_acyclic' => true,
                'calculation_order' => $calculationOrder,
            ]);
        }

        return $next($evaluation);
    }

    /**
     * @return array<string, list<string>>
     */
    private function calculationDependencies(Configurator $configurator): array
    {
        $formulas = $configurator->calculations->pluck('expression', 'name')->all();
        $dependencies = [];

        foreach ($formulas as $name => $expression) {
            preg_match_all('/calculation\.([a-zA-Z_][a-zA-Z0-9_]*)/', $expression, $matches);
            $dependencies[$name] = array_values(array_intersect(array_unique($matches[1]), array_keys($formulas)));
        }

        return $dependencies;
    }

    /**
     * @return array<string, list<string>>
     */
    private function labelReferences(Configurator $configurator): array
    {
        $views = $configurator->rootView->descendantsAndSelf()->with('templates')->get();
        $viewIds = $views->pluck('id')->all();
        $dependencies = [];

        foreach ($views as $view) {
            $labelTemplate = $view->templates->firstWhere('key', 'label');

            preg_match_all('/\blabel\(\s*[\'"]([^\'"]+)[\'"]\s*\)/', $labelTemplate?->template ?? '', $matches);
            $dependencies[$view->id] = array_values(array_intersect(array_unique($matches[1]), $viewIds));
        }

        return $dependencies;
    }

    /**
     * @param  array<string, list<string>>  $dependenciesByName
     * @return list<string>
     */
    private function topologicalOrder(array $dependenciesByName, Closure $message): array
    {
        $resolved = [];
        $visiting = [];

        $visit = function (string $name) use (&$visit, &$resolved, &$visiting, $dependenciesByName, $message): void {
            if (in_array($name, $resolved, true)) {
                return;
            }

            if (in_array($name, $visiting, true)) {
                throw new RuntimeException($message($name));
            }

            $visiting[] = $name;

            foreach ($dependenciesByName[$name] as $dependency) {
                $visit($dependency);
            }

            $visiting = array_diff($visiting, [$name]);
            $resolved[] = $name;
        };

        foreach (array_keys($dependenciesByName) as $name) {
            $visit($name);
        }

        return $resolved;
    }
}
