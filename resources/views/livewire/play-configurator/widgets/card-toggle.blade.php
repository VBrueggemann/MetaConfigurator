<label class="group relative flex w-40 cursor-pointer flex-col items-center gap-2 rounded-xl border-2 border-gray-200 bg-white px-5 py-4 text-center shadow-sm transition hover:border-blue-300 hover:shadow-md has-[:checked]:border-blue-600 has-[:checked]:bg-blue-50 has-[:checked]:shadow-md">
    <input
        type="checkbox"
        wire:model.live{{ ($item->config['updateOn'] ?? 'blur') === 'live' ? '' : '.blur' }}="values.{{ $item->id }}"
        class="sr-only"
    />
    @if ($item->config['icon'] ?? null)
        <span class="text-3xl" aria-hidden="true">{{ $item->config['icon'] }}</span>
    @endif
    <span class="font-semibold text-gray-900">{{ $item->config['title'] ?? '' }}</span>
    @if ($item->config['subtitle'] ?? null)
        <span class="text-xs text-gray-500">{{ $item->config['subtitle'] }}</span>
    @endif
</label>
