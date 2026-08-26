<input
    type="email"
    wire:model.live{{ ($item->config['updateOn'] ?? 'blur') === 'live' ? '' : '.blur' }}="values.{{ $item->id }}"
    class="w-full rounded border border-gray-300 px-3 py-2"
/>
