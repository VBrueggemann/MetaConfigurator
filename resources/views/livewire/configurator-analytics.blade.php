<div class="mx-auto max-w-4xl p-6">
    <div class="mb-8 flex items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold text-gray-900">{{ $configurator->name }} — Analytics</h1>
            <p class="mt-1 text-sm text-gray-600">
                {{ $analytics->totalConfigurations }} configuration{{ $analytics->totalConfigurations === 1 ? '' : 's' }} total
            </p>
        </div>
        <a href="{{ route('configurators.play', $configurator) }}" class="shrink-0 text-sm text-blue-600 underline hover:text-blue-700">
            Open configurator
        </a>
    </div>

    @if ($analytics->anchors->isEmpty())
        <p class="text-sm text-gray-600">No answers have been recorded for this configurator yet.</p>
    @endif

    <div class="space-y-6">
        @foreach ($analytics->anchors as $anchor)
            <div class="rounded-lg border border-gray-200 p-5">
                <div class="mb-4 flex items-baseline justify-between gap-4">
                    <h2 class="text-base font-semibold text-gray-900">
                        {{ $anchor->view->config['label'] ?? 'Step '.$loop->iteration }}
                    </h2>
                    <span class="shrink-0 text-sm text-gray-600">
                        {{ $anchor->visitCount }} configuration{{ $anchor->visitCount === 1 ? '' : 's' }} reached this step
                    </span>
                </div>

                @if ($anchor->answers->isNotEmpty())
                    <div class="mb-5 space-y-4">
                        @foreach ($anchor->answers as $fieldAnswers)
                            <div>
                                <h3 class="mb-2 text-sm font-medium text-gray-700">
                                    {{ $fieldAnswers['view']->config['label'] ?? 'Answer' }}
                                </h3>
                                <ul class="space-y-1.5">
                                    @foreach ($fieldAnswers['values'] as $valueStat)
                                        <li class="flex items-center gap-3 text-sm">
                                            <span class="w-40 shrink-0 truncate text-gray-700">
                                                {{ $this->formatValue($fieldAnswers['view'], $valueStat['value']) }}
                                            </span>
                                            <span class="h-2 grow overflow-hidden rounded-full bg-gray-100">
                                                <span class="block h-full rounded-full bg-blue-500" style="width: {{ $valueStat['percentage'] }}%"></span>
                                            </span>
                                            <span class="w-24 shrink-0 text-right text-gray-600">
                                                {{ $valueStat['count'] }} ({{ $valueStat['percentage'] }}%)
                                            </span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div>
                    <h3 class="mb-2 text-sm font-medium text-gray-700">Next step</h3>
                    <ul class="space-y-1.5">
                        @foreach ($anchor->transitions as $transition)
                            <li class="flex items-center gap-2 text-sm text-gray-700">
                                <span aria-hidden="true">&rarr;</span>
                                <span class="grow">
                                    {{ $transition['view'] ? ($transition['view']->config['label'] ?? 'Untitled step') : 'Finished the configurator' }}
                                </span>
                                <span class="shrink-0 text-gray-600">
                                    {{ $transition['count'] }} ({{ $transition['percentage'] }}%)
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endforeach
    </div>
</div>
