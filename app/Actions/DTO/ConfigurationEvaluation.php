<?php

namespace App\Actions\DTO;

use App\Models\Configuration;

final class ConfigurationEvaluation
{
    /**
     * @param  array<int, PendingAnswer>  $pendingAnswers
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $componentContexts
     */
    public function __construct(
        public readonly Configuration $configuration,
        public array $pendingAnswers,
        public array $context = [],
        public array $componentContexts = [],
        public ?string $anchorViewId = null,
    ) {}
}
