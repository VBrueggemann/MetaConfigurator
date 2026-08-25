<input
    type="text"
    list="{{ $item->id }}-options"
    wire:model.live.blur="values.{{ $item->id }}"
    class="w-full rounded border border-gray-300 px-3 py-2"
/>
<datalist id="{{ $item->id }}-options">
    @foreach ($item->config['options'] ?? [] as $option)
        <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
    @endforeach
</datalist>
