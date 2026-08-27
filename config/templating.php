<?php

use App\Actions\Pipeline\ResolveTemplate;
use App\Enums\ComponentType;
use App\Enums\RuleComponent;
use App\Models\Rule;
use App\Models\View;
use Illuminate\Support\Collection;

return [
    'functions' => [
        View::class => [
            'label' => [
                'with' => [],
                'resolve' => fn (View $view, array $context) => isset($view->config['label'])
                    ? app(ResolveTemplate::class)($view->config['label'], $context)
                    : null,
            ],
            'answer' => [
                'with' => ['descendants'],
                'resolve' => function (View $view, array $context): mixed {
                    $viewIds = [$view->id, ...$view->descendants->pluck('id')];

                    $persisted = collect($context['relevantAnswers'] ?? [])->whereIn('view_id', $viewIds)->pluck('value', 'view_id');
                    $pending = ($context['__persistedOnly'] ?? false)
                        ? collect()
                        : collect($context['pendingAnswers'] ?? [])->whereIn('viewId', $viewIds)->pluck('value', 'viewId');
                    $merged = $persisted->merge($pending);

                    if ($merged->isNotEmpty()) {
                        return $merged->values()->implode(', ');
                    }

                    return collect($context['relevantAnswerChunks'] ?? [])->contains(fn ($chunk) => $chunk->anchor_view_id === $view->id) ? true : null;
                },
            ],
            'validationError' => [
                'with' => ['rules'],
                'resolve' => fn (View $view, array $context) => $view->rules
                    ->where('component', RuleComponent::Validation)
                    ->pluck('meta.message')
                    ->filter()
                    ->implode(', '),
            ],
        ],
    ],

    'resolvable' => [
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
    ],
];
