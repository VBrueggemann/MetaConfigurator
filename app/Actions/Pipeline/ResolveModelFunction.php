<?php

namespace App\Actions\Pipeline;

use RuntimeException;

final class ResolveModelFunction
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __invoke(array $context, string $modelClass, string $id, string $function): mixed
    {
        $key = "{$modelClass}:{$id}:{$function}";

        /** @var list<string> $resolving */
        $resolving = $context['__resolving'] ?? [];

        if (in_array($key, $resolving, true)) {
            throw new RuntimeException("Circular reference involving '{$key}'.");
        }

        $model = $context['modelsById'][$modelClass][$id] ?? null;

        if ($model === null) {
            throw new RuntimeException("Unknown reference '{$key}'.");
        }

        $definition = config("templating.functions.{$modelClass}.{$function}");

        return ($definition['resolve'])($model, [...$context, '__resolving' => [...$resolving, $key]]);
    }
}
