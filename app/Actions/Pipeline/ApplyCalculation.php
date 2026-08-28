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
        $order = $evaluation->configuration->configurator->calculation_order;

        $evaluation->context['calculation'] = (object) $this->resolve($formulas, $order, $evaluation->context);

        return $next($evaluation);
    }

    /**
     * @param  array<string, string>  $formulas
     * @param  list<string>  $order
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function resolve(array $formulas, array $order, array $context): array
    {
        $results = [];

        foreach ($order as $name) {
            try {
                $results[$name] = ($this->evaluateExpression)($formulas[$name], [...$context, 'calculation' => (object) $results]);
            } catch (Throwable $e) {
                report($e);

                $results[$name] = 0;
            }
        }

        return $results;
    }
}
