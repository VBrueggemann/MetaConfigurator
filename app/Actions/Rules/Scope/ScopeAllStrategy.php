<?php

namespace App\Actions\Rules\Scope;

use App\Models\Configuration;
use Staudenmeir\LaravelAdjacencyList\Eloquent\Relations\Descendants;

final class ScopeAllStrategy
{
    public function __invoke(Configuration $configuration): Descendants
    {
        return $configuration->configurator->rootView->descendantsAndSelf();
    }
}
