<?php

use App\Models\Calculation;
use App\Models\Configurator;
use App\Models\View;

return [
    'graphs' => [
        [
            'items' => fn (Configurator $configurator) => $configurator->calculations->keyBy('name'),
            'dependenciesOf' => function (Calculation $calculation): array {
                preg_match_all('/calculation\.([a-zA-Z_][a-zA-Z0-9_]*)/', $calculation->expression, $matches);

                return array_unique($matches[1]);
            },
            'onResolved' => function (Calculation $calculation, array $resolved): void {
                $calculation->update(['order' => array_search($calculation, $resolved)]);
            },
        ],
        [
            'items' => fn (Configurator $configurator) => $configurator->rootView->descendantsAndSelf()->with('templates')->get()->keyBy('id'),
            'dependenciesOf' => function (View $view): array {
                $labelTemplate = $view->templates->firstWhere('key', 'label');

                preg_match_all('/\blabel\(\s*[\'"]([^\'"]+)[\'"]\s*\)/', $labelTemplate?->template ?? '', $matches);

                return array_unique($matches[1]);
            },
        ],
    ],
];
