<?php

namespace App\Actions\Pipeline;

use App\Actions\DTO\ConfigurationEvaluation;
use Closure;
use Illuminate\Support\Collection;
use RuntimeException;

final class EnsureConfiguratorBaked
{
    public function __invoke(ConfigurationEvaluation $evaluation, Closure $next): mixed
    {
        $configurator = $evaluation->configuration->configurator;

        if ($configurator->is_baked) {
            return $next($evaluation);
        }

        foreach (config('baking.graphs') as $graph) {
            $this->resolveInDependencyOrder(
                $graph['items']($configurator),
                $graph['dependenciesOf'],
                $graph['onResolved'] ?? null,
                $graph['message'] ?? null,
            );
        }

        $configurator->update(['is_baked' => true]);

        return $next($evaluation);
    }

    /**
     * @param  Collection<array-key, object>  $itemsByKey
     * @param  Closure(object): iterable<array-key>  $dependenciesOf
     * @param  ?Closure(object, array): void  $onResolved
     * @param  ?Closure(object): string  $message
     */
    private function resolveInDependencyOrder(Collection $itemsByKey, Closure $dependenciesOf, ?Closure $onResolved = null, ?Closure $message = null): void
    {
        $resolved = [];
        $visiting = [];

        $visit = function ($key) use (&$visit, &$resolved, &$visiting, $itemsByKey, $dependenciesOf, $onResolved, $message): void {
            if (!$itemsByKey->has($key) || in_array($itemsByKey[$key], $resolved)) {
                return;
            }

            if (isset($visiting[$key])) {
                throw new RuntimeException(
                    $message !== null ? $message($itemsByKey[$key]) : "Circular dependency involving '{$key}' of type '" . get_class($itemsByKey->first()) . "'."
                );
            }

            $visiting[$key] = true;

            foreach ($dependenciesOf($itemsByKey[$key]) as $dependencyKey) {
                $visit($dependencyKey);
            }

            unset($visiting[$key]);
            $resolved[] = $itemsByKey[$key];

            if (is_callable($onResolved)) {
                $onResolved($itemsByKey[$key], $resolved);
            }
        };

        foreach ($itemsByKey->keys() as $key) {
            $visit($key);
        }
    }
}
