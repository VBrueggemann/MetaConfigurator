<?php

use App\Actions\Pipeline\ResolveTemplate;
use App\Enums\ComponentType;
use App\Models\Rule;
use App\Models\View;
use Illuminate\Support\Collection;

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
        [
            'context' => ComponentType::Scope->value,
            'iterate' => function (Collection $views, Closure $visit): void {
                $walk = function (View $view) use (&$walk, $visit): void {
                    $visit($view);
                    $view->children->each($walk);
                };

                $views->each($walk);
            },
            'resolve' => function (View $view, array $keys, ResolveTemplate $resolveTemplate, array $context): void {
                $config = $view->config;

                foreach ($keys as $key => $resolver) {
                    if (isset($config[$key])) {
                        $config[$key] = $resolver($config[$key], $resolveTemplate, $context);
                    }
                }

                $view->config = $config;
            },
            'keys' => [
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

        [
            'context' => ComponentType::Scope->value,
            'iterate' => function (Collection $views, Closure $visit): void {
                $walk = function (View $view) use (&$walk, $visit): void {
                    $view->rules->each($visit);
                    $view->children->each($walk);
                };

                $views->each($walk);
            },
            'resolve' => function (Rule $rule, array $keys, ResolveTemplate $resolveTemplate, array $context): void {
                $meta = $rule->meta;

                foreach ($keys as $key => $resolver) {
                    if (isset($meta[$key])) {
                        $meta[$key] = $resolver($meta[$key], $resolveTemplate, $context);
                    }
                }

                $rule->meta = $meta;
            },
            'keys' => [
                'message' => fn (string $template, ResolveTemplate $resolveTemplate, array $context) => $resolveTemplate($template, $context),
            ],
        ],
    ],
];
