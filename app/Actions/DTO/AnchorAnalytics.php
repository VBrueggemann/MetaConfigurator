<?php

namespace App\Actions\DTO;

use App\Models\View;
use Illuminate\Support\Collection;

final class AnchorAnalytics
{
    /**
     * @param  Collection<int, array{view: View, values: Collection<int, array{value: mixed, count: int, percentage: float}>}>  $answers
     * @param  Collection<int, array{view: ?View, count: int, percentage: float}>  $transitions
     */
    public function __construct(
        public readonly View $view,
        public readonly int $visitCount,
        public readonly Collection $answers,
        public readonly Collection $transitions,
    ) {}
}
