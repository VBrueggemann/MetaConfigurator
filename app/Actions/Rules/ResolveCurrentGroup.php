<?php

namespace App\Actions\Rules;

use App\Enums\RuleComponent;
use App\Models\Configuration;
use App\Models\View;
use RuntimeException;

final class ResolveCurrentGroup
{
    public function __construct(
        private readonly EvaluateGroup $evaluateGroup,
    ) {}

    public function __invoke(Configuration $configuration): View
    {
        $group = View::query()
            ->where('configurator_id', $configuration->configurator_id)
            ->whereNull('parent_id')
            ->orderBy('order')
            ->firstOrFail();

        $maxSteps = View::query()
            ->where('configurator_id', $configuration->configurator_id)
            ->whereNull('parent_id')
            ->count();

        for ($step = 0; $step <= $maxSteps; $step++) {
            $evaluation = ($this->evaluateGroup)($group, $configuration);

            if (! empty($evaluation->componentContexts[RuleComponent::Validation->value] ?? [])) {
                return $group;
            }

            $nextId = $evaluation->componentContexts[RuleComponent::NextGroup->value] ?? null;

            if ($nextId === null) {
                return $group;
            }

            $group = View::query()->where('id', $nextId)->firstOrFail();
        }

        throw new RuntimeException("Configurator {$configuration->configurator_id} has a next-group cycle.");
    }
}
