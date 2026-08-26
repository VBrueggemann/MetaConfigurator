<div wire:key="{{ $item->id }}">
    @if ($item->config['label'] ?? false)
        <label class="mb-1 block font-medium text-gray-900">{{ $item->config['label'] ?? '' }}</label>
    @endif
    @if ($item->type->value === 'group')
        @if (($item->config['widget'] ?? null) === 'multiselect')
            @include('livewire.play-configurator.widgets.group.multiselect', ['item' => $item])
        @else
            <div class="flex {{ ($item->config['direction'] ?? 'vertical') === 'horizontal' ? 'flex-row flex-wrap gap-4' : 'flex-col space-y-6' }}">
                @foreach ($item->children as $child)
                    @if (in_array($child->id, $visibleViewIds, true))
                        @include('livewire.play-configurator.item', ['item' => $child])
                    @endif
                @endforeach
            </div>
        @endif
    @elseif ($item->type->value === 'display')
        <div class="prose prose-sm max-w-none text-gray-700">{!! $item->config['content'] ?? '' !!}</div>
    @else
        @include('livewire.play-configurator.widgets.'.($item->config['widget'] ?? ''), ['item' => $item])

        @if (! empty($fieldErrors[$item->id] ?? null))
            <ul class="mt-1 text-sm text-red-600">
                @foreach ($fieldErrors[$item->id] as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        @endif
    @endif
</div>
