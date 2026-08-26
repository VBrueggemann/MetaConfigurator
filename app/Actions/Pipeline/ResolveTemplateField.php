<?php

namespace App\Actions\Pipeline;

use RuntimeException;

final class ResolveTemplateField
{
    public function __construct(
        private readonly ResolveTemplate $resolveTemplate,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function __invoke(array $context, string $namespace, string $id, string $field): string
    {
        $key = "{$namespace}.{$id}.{$field}";

        /** @var list<string> $resolving */
        $resolving = $context['__resolving'] ?? [];

        if (in_array($key, $resolving, true)) {
            throw new RuntimeException("Circular template reference involving '{$key}'.");
        }

        $raw = $context['templateFields'][$namespace][$id][$field] ?? null;

        if ($raw === null) {
            throw new RuntimeException("Unknown template reference '{$key}'.");
        }

        return ($this->resolveTemplate)($raw, [...$context, '__resolving' => [...$resolving, $key]]);
    }
}
