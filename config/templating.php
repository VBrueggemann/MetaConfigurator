<?php

use App\Actions\Pipeline\ResolveTemplate;
use App\Models\Rule;
use App\Models\View;

return [
    'fields' => [
        View::class => [
            'label' => fn (View $view) => $view->config['label'] ?? null,
        ],
        Rule::class => [
            'message' => fn (Rule $rule) => $rule->meta['message'] ?? null,
        ],
    ],

    'resolvable' => [
        View::class => [
            'label' => fn (string $template, ResolveTemplate $resolveTemplate, array $context) => $resolveTemplate($template, $context),
            'content' => fn (string $template, ResolveTemplate $resolveTemplate, array $context) => $resolveTemplate($template, $context, escapeValues: true),
            'options' => function (array $options, ResolveTemplate $resolveTemplate, array $context) {
                return array_map(
                    fn (array $option) => isset($option['label'])
                        ? [...$option, 'label' => $resolveTemplate($option['label'], $context)]
                        : $option,
                    $options,
                );
            },
        ],
    ],
];
