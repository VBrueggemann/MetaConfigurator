<?php

namespace App\Actions\Rules\Scope;

use App\Models\Configuration;
use App\Models\View;

final class CurrentStep
{
    public function __invoke(Configuration $configuration, int $depth): ?View
    {
        $rootView = $configuration->configurator->rootView;
        $answeredViewIds = $configuration->answers()->pluck('view_id');

        return $rootView->descendantsAndSelf()->whereDepth($depth)->whereNotIn('id', $answeredViewIds)->orderBy('order')->first()
            ?? $rootView->descendantsAndSelf()->whereDepth($depth)->orderByDesc('order')->first();
    }
}
