<input
    type="checkbox"
    wire:model.live{{ ($item->config['updateOn'] ?? 'blur') === 'live' ? '' : '.blur' }}="values.{{ $item->id }}"
    class="h-4 w-4 rounded border-gray-300"
/>
