<select wire:model.live.blur="values.{{ $item->id }}" class="w-full rounded border border-gray-300 px-3 py-2">
    <option value="">—</option>
    @foreach ($item->config['options'] ?? [] as $option)
        <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
    @endforeach
</select>
