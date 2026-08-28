<?php

namespace App\Queries;

use App\Models\AnswerChunk;
use App\Models\Configurator;
use Illuminate\Database\Eloquent\Builder;

final class GetRelevantAnswerChunksForConfigurator
{
    public function __invoke(Configurator $configurator): Builder
    {
        return AnswerChunk::query()
            ->where('relevant', true)
            ->whereHas('configuration', fn (Builder $query) => $query->where('configurator_id', $configurator->id));
    }
}
