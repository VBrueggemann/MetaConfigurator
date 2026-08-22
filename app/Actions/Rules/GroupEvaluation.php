<?php

namespace App\Actions\Rules;

use App\Models\View;

final class GroupEvaluation
{
    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $componentContexts  Keyed by RuleComponent value — each
     *                                                   pipeline stage owns and writes only its own key.
     */
    public function __construct(
        public readonly View $group,
        public readonly array $context,
        public array $componentContexts = [],
    ) {}
}
