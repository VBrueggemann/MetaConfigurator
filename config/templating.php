<?php

use App\Actions\Pipeline\ResolveTemplate;
use App\Enums\RuleComponent;
use App\Enums\ViewType;
use App\Models\Rule;
use App\Models\View;
use Illuminate\Support\Collection;

return [
    'functions' => [
        View::class => [
            'label' => [
                'with' => ['templates'],
                'resolve' => function (View $view, array $context): mixed {
                    $template = $view->templates->firstWhere('key', 'label');

                    return $template !== null ? app(ResolveTemplate::class)($template->template, $context) : null;
                },
            ],
            'answer' => [
                'with' => ['descendants'],
                'resolve' => function (View $view, array $context): mixed {
                    $viewIds = $view->type === ViewType::Group
                        ? [$view->id, ...$view->descendants->pluck('id')]
                        : [$view->id];

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
            'context' => 'scope',
            'iterate' => function (Collection $views, Closure $visit): void {
                $walk = function (View $view) use (&$walk, $visit): void {
                    $view->rules->each($visit);
                    $view->children->each($walk);
                };

                $views->each($walk);
            },
            'resolve' => function (Rule $rule, array $keys, ResolveTemplate $resolveTemplate, array $context): void {
                $meta = $rule->meta ?? [];
                $templatesByKey = $rule->templates->groupBy('key');

                foreach ($keys as $key => $resolver) {
                    $meta = $resolver($meta, $templatesByKey->get($key, collect()), $resolveTemplate, $context);
                }

                $rule->meta = $meta;
            },
            'keys' => [
                'message' => function (array $meta, Collection $templates, ResolveTemplate $resolveTemplate, array $context): array {
                    $template = $templates->first();

                    if ($template !== null) {
                        $meta['message'] = $resolveTemplate($template->template, $context);
                    }

                    return $meta;
                },
            ],
        ],

        [
            'context' => 'scope',
            'iterate' => function (Collection $views, Closure $visit): void {
                $walk = function (View $view) use (&$walk, $visit): void {
                    $visit($view);
                    $view->children->each($walk);
                };

                $views->each($walk);
            },
            'resolve' => function (View $view, array $keys, ResolveTemplate $resolveTemplate, array $context): void {
                $config = $view->config;
                $templatesByKey = $view->templates->groupBy('key');

                foreach ($keys as $key => $resolver) {
                    $config = $resolver($config, $templatesByKey->get($key, collect()), $resolveTemplate, $context);
                }

                $view->config = $config;
            },
            'keys' => [
                'label' => function (array $config, Collection $templates, ResolveTemplate $resolveTemplate, array $context): array {
                    $template = $templates->first();

                    if ($template !== null) {
                        $config['label'] = $resolveTemplate($template->template, $context);
                    }

                    return $config;
                },
                'content' => function (array $config, Collection $templates, ResolveTemplate $resolveTemplate, array $context): array {
                    $template = $templates->first();

                    if ($template !== null) {
                        $config['content'] = $resolveTemplate($template->template, $context, escapeValues: true);
                    }

                    return $config;
                },
                'option_label' => function (array $config, Collection $templates, ResolveTemplate $resolveTemplate, array $context): array {
                    if (! isset($config['options'])) {
                        return $config;
                    }

                    $config['options'] = collect($config['options'])->values()->map(
                        function (array $option, int $index) use ($templates, $resolveTemplate, $context) {
                            $template = $templates->firstWhere('order', $index);

                            return $template !== null
                                ? [...$option, 'label' => $resolveTemplate($template->template, $context)]
                                : $option;
                        },
                    )->all();

                    return $config;
                },
            ],
        ],
    ],
];
