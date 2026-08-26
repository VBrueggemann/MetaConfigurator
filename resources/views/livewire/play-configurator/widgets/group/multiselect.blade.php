<select
    multiple
    wire:model.live="groupSelections.{{ $item->id }}"
    class="w-full rounded border border-gray-300 px-3 py-2"
>
    @foreach ($item->children as $child)
        <option value="{{ $child->id }}" @selected($values[$child->id] ?? false)>{{ $child->config['label'] ?? '' }}</option>
    @endforeach
</select>
