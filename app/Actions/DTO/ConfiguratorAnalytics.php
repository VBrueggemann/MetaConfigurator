<?php

namespace App\Actions\DTO;

use App\Models\Configurator;
use Illuminate\Support\Collection;

final class ConfiguratorAnalytics
{
    /**
     * @param  Collection<int, AnchorAnalytics>  $anchors
     */
    public function __construct(
        public readonly Configurator $configurator,
        public readonly int $totalConfigurations,
        public readonly Collection $anchors,
    ) {}
}
