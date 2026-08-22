<?php

namespace App\Actions\Rules;

use App\Models\Configuration;
use App\Models\View;
use Illuminate\Support\Collection;

final class BuildAnswerContext
{
    public function __invoke(Configuration $configuration): array
    {
        $answersByViewId = $configuration->answers()->get()->pluck('value', 'view_id');

        $answers = [];
        foreach (View::query()->where('configurator_id', $configuration->configurator_id)->whereNull('parent_id')->orderBy('order')->get() as $group) {
            $this->walk($group, $answersByViewId, $answers);
        }

        return ['answers' => $answers];
    }

    /**
     * Populates $answers with this view's own entry (a scalar for a leaf,
     * or a nested map of its children for a group), and recurses into
     * children first so a group's nested map can be built from them.
     */
    private function walk(View $view, Collection $answersByViewId, array &$answers): void
    {
        $children = View::query()->where('parent_id', $view->id)->orderBy('order')->get();

        if ($children->isNotEmpty()) {
            $nested = [];

            foreach ($children as $child) {
                $this->walk($child, $answersByViewId, $answers);

                if (array_key_exists($child->id, $answers)) {
                    $nested[$child->id] = $answers[$child->id];
                }
            }

            $answers[$view->id] = $nested;

            return;
        }

        if ($answersByViewId->has($view->id)) {
            $answers[$view->id] = $answersByViewId->get($view->id);
        }
    }
}
