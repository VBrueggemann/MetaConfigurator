    @if ($item->type->value === 'group')
        @foreach ($item->children as $child)
            @include('livewire.play-configurator.item', ['item' => $child])
        @endforeach
    @else
        <div wire:key="{{ $item->id }}">
            @if ($item->type->value === 'html')
                <div class="prose prose-sm max-w-none text-gray-700">{!! $item->config['content'] ?? '' !!}</div>
            @else
                <label class="mb-1 block font-medium text-gray-900">{{ $item->config['label'] ?? '' }}</label>

                @if ($item->type->value === 'text_input')
                    <input
                        type="text"
                        wire:model.live.blur="values.{{ $item->id }}"
                        class="w-full rounded border border-gray-300 px-3 py-2"
                    />
                @elseif ($item->type->value === 'checkbox')
                    <input
                        type="checkbox"
                        wire:model.live.blur="values.{{ $item->id }}"
                        class="h-4 w-4 rounded border-gray-300"
                    />
                @elseif ($item->type->value === 'select')
                    <select wire:model.live.blur="values.{{ $item->id }}" class="w-full rounded border border-gray-300 px-3 py-2">
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
    @endif
