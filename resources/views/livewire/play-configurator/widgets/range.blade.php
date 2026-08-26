<input
    type="range"
    wire:model.live{{ ($item->config['updateOn'] ?? 'blur') === 'live' ? '' : '.blur' }}="values.{{ $item->id }}"
    min="{{ $item->config['min'] ?? 0 }}"
    max="{{ $item->config['max'] ?? 100 }}"
    class="w-full"
/>
