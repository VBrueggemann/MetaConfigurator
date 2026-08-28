<?php

namespace App\Actions\Pipeline;

use App\Actions\DTO\ConfigurationEvaluation;
use Closure;
use Throwable;

final class ApplyCalculation
{
    public function __construct(
        private readonly EvaluateExpression $evaluateExpression,
    ) {}

    public function __invoke(ConfigurationEvaluation $evaluation, Closure $next): mixed
    {
        if (! array_key_exists('calculationFormulas', $evaluation->context)) {
            $evaluation->context['calculationFormulas'] = $evaluation->configuration->configurator->calculations
                ->pluck('expression', 'name')
                ->all();
        }

        /** @var array<string, string> $formulas */
        $formulas = $evaluation->context['calculationFormulas'];

        $evaluation->context['calculation'] = (object) $this->resolve($formulas, $evaluation->context);

        return $next($evaluation);
    }

    /**
     * @param  array<string, string>  $formulas
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function resolve(array $formulas, array $context): array
    {
        $results = [];

        foreach ($this->topologicalOrder($formulas) as $name) {
            try {
                $results[$name] = ($this->evaluateExpression)($formulas[$name], [...$context, 'calculation' => (object) $results]);
            } catch (Throwable $e) {
                report($e);

                $results[$name] = 0;
            }
        }

        return $results;
    }

    /**
     * Orders formulas so a formula referencing `calculation.<name>` is evaluated after `<name>`.
     * Assumes an acyclic dependency graph — `BakeConfiguratorGraphs` asserts that once per
     * configurator before this stage ever runs.
     *
     * @param  array<string, string>  $formulas
     * @return list<string>
     */
    private function topologicalOrder(array $formulas): array
    {
        $dependenciesByName = [];

        foreach ($formulas as $name => $expression) {
            preg_match_all('/calculation\.([a-zA-Z_][a-zA-Z0-9_]*)/', $expression, $matches);
            $dependenciesByName[$name] = array_values(array_intersect(array_unique($matches[1]), array_keys($formulas)));
        }

        $resolved = [];

        $visit = function (string $name) use (&$visit, &$resolved, $dependenciesByName): void {
            if (in_array($name, $resolved, true)) {
                return;
            }

            foreach ($dependenciesByName[$name] as $dependency) {
                $visit($dependency);
            }

            $resolved[] = $name;
        };

        foreach (array_keys($formulas) as $name) {
            $visit($name);
        }

        return $resolved;
    }
}
