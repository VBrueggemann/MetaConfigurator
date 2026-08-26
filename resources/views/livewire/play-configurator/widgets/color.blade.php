<input
    type="color"
    wire:model.live{{ ($item->config['updateOn'] ?? 'blur') === 'live' ? '' : '.blur' }}="values.{{ $item->id }}"
    class="h-10 w-16 rounded border border-gray-300"
/>
