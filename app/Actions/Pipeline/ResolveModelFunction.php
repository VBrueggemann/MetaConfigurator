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
        $model = $context['modelsById'][$modelClass][$id] ?? null;

        if ($model === null) {
            throw new RuntimeException("Unknown reference '{$modelClass}:{$id}:{$function}'.");
        }

        $definition = config("templating.functions.{$modelClass}.{$function}");

        return ($definition['resolve'])($model, $context);
    }
}
