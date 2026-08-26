<div class="space-y-1">
    @foreach ($item->config['options'] ?? [] as $option)
        <label class="flex items-center gap-2">
            <input
                type="radio"
                name="{{ $item->id }}"
                value="{{ $option['value'] }}"
                wire:model.live{{ ($item->config['updateOn'] ?? 'blur') === 'live' ? '' : '.blur' }}="values.{{ $item->id }}"
                class="h-4 w-4 border-gray-300"
            />
            {{ $option['label'] }}
        </label>
    @endforeach
</div>
