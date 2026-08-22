<div class="mx-auto max-w-xl p-6">
    <h1 class="mb-6 text-xl font-semibold text-gray-900">{{ $configurator->name }}</h1>

    <div class="space-y-6">
        @foreach ($items as $item)
            @continue(! in_array($item->id, $visibleViewIds, true))

            <div wire:key="{{ $item->id }}">
                @if ($item->type->value === 'html')
                    <div class="prose prose-sm max-w-none text-gray-700">{!! $item->config['content'] ?? '' !!}</div>
                @else
                    <label class="mb-1 block font-medium text-gray-900">{{ $item->config['label'] ?? '' }}</label>

                    @if ($item->type->value === 'text_input')
                        <input
                            type="text"
                            wire:model.live.debounce.500ms="values.{{ $item->id }}"
                            class="w-full rounded border border-gray-300 px-3 py-2"
                        />
                    @elseif ($item->type->value === 'select' && ($item->config['multiple'] ?? false))
                        <div class="space-y-1">
                            @foreach ($item->config['options'] ?? [] as $option)
                                <label class="flex items-center gap-2">
                                    <input
                                        type="checkbox"
                                        value="{{ $option['value'] }}"
                                        wire:model.live="values.{{ $item->id }}"
                                    />
                                    {{ $option['label'] }}
                                </label>
                            @endforeach
                        </div>
                    @elseif ($item->type->value === 'select')
                        <select wire:model.live="values.{{ $item->id }}" class="w-full rounded border border-gray-300 px-3 py-2">
                            <option value="">—</option>
                            @foreach ($item->config['options'] ?? [] as $option)
                                <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                    @endif

                    @if (! empty($fieldErrors[$item->id] ?? null))
                        <ul class="mt-1 text-sm text-red-600">
                            @foreach ($fieldErrors[$item->id] as $message)
                                <li>{{ $message }}</li>
                            @endforeach
                        </ul>
                    @endif
                @endif
            </div>
        @endforeach
    </div>

    @unless ($nextGroupId === null && empty($fieldErrors))
        <button
            type="button"
            wire:click="next"
            class="mt-6 rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700"
        >
            Next
        </button>
    @endunless
</div>
