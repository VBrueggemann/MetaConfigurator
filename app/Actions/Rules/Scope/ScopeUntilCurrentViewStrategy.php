<?php

namespace App\Actions\Rules\Scope;

use App\Models\Configuration;
use Illuminate\Database\Eloquent\Builder;
use Staudenmeir\LaravelAdjacencyList\Eloquent\Relations\Descendants;

final class ScopeUntilCurrentViewStrategy
{
    public function __construct(
        private readonly CurrentStep $currentStep,
    ) {}

    public function __invoke(Configuration $configuration, int $depth = 0): Descendants
    {
        $rootView = $configuration->configurator->rootView;
        $currentView = ($this->currentStep)($configuration, $depth);
        $answeredViewIds = $configuration->answers()->pluck('view_id');

        return $rootView->descendantsAndSelf()->where(function (Builder $query) use ($depth, $currentView, $answeredViewIds) {
            $query->whereDepth('<', $depth);

            if ($currentView !== null) {
                $query->orWhereHas('ancestorsAndSelf', fn (Builder $q) => $q->where('id', $currentView->id));
            }

            $query->orWhereHas('descendantsAndSelf', fn (Builder $q) => $q->whereIn('id', $answeredViewIds));
        });
    }
}
