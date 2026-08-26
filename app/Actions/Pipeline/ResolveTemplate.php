<?php

namespace App\Actions\Pipeline;

use Throwable;

final class ResolveTemplate
{
    public function __construct(
        private readonly EvaluateExpression $evaluateExpression,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function __invoke(string $template, array $context, bool $escapeValues = false): string
    {
        return preg_replace_callback(
            '/\{\{\s*(.+?)\s*\}\}/',
            function (array $matches) use ($context, $escapeValues): string {
                try {
                    $result = ($this->evaluateExpression)($matches[1], $context);
                } catch (Throwable) {
                    return $matches[0];
                }

                if ($result === null) {
                    return $matches[0];
                }

                $value = $this->stringify($result);

                return $escapeValues ? e($value) : $value;
            },
            $template,
        ) ?? $template;
    }

    private function stringify(mixed $result): string
    {
        if (is_array($result)) {
            return implode(', ', array_map($this->stringify(...), $result));
        }

        if (is_bool($result)) {
            return $result ? 'true' : 'false';
        }

        return (string) $result;
    }
}
