<?php

namespace App\Actions\Rules;

use App\Models\Configuration;
use App\Models\View;
use Illuminate\Pipeline\Pipeline;

final class EvaluateGroup
{
    public function __construct(
        private readonly BuildAnswerContext $buildAnswerContext,
    ) {}

    public function __invoke(View $group, Configuration $configuration): GroupEvaluation
    {
        $evaluation = new GroupEvaluation($group, ($this->buildAnswerContext)($configuration));

        return app(Pipeline::class)
            ->send($evaluation)
            ->through([ApplyVisibility::class, ApplyValidation::class, ResolveNextGroup::class])
            ->via('__invoke')
            ->then(fn (GroupEvaluation $evaluation): GroupEvaluation => $evaluation);
    }
}
