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
     * Populates $answers with this view's own entry, then recurses into
     * children so their entries are populated too. A direct Answer against
     * this view's own id (e.g. a group acknowledged via Answer::updateOrCreate)
     * always takes precedence over the nested map of its children's entries —
     * a container view isn't only ever "the sum of its children".
     */
    private function walk(View $view, Collection $answersByViewId, array &$answers): void
    {
        $children = View::query()->where('parent_id', $view->id)->orderBy('order')->get();

        foreach ($children as $child) {
            $this->walk($child, $answersByViewId, $answers);
        }

        if ($answersByViewId->has($view->id)) {
            $answers[$view->id] = $answersByViewId->get($view->id);

            return;
        }

        if ($children->isNotEmpty()) {
            $nested = [];

            foreach ($children as $child) {
                if (array_key_exists($child->id, $answers)) {
                    $nested[$child->id] = $answers[$child->id];
                }
            }

            $answers[$view->id] = $nested;
        }
    }
}
