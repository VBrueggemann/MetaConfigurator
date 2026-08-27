<div class="flex flex-row flex-wrap gap-3">
    @foreach ($item->config['options'] ?? [] as $option)
        <label class="group relative flex w-44 cursor-pointer flex-col gap-1 rounded-xl border-2 border-gray-200 bg-white p-4 shadow-sm transition hover:border-blue-300 hover:shadow-md has-[:checked]:border-blue-600 has-[:checked]:bg-blue-50 has-[:checked]:shadow-md">
            <input
                type="radio"
                name="{{ $item->id }}"
                value="{{ $option['value'] }}"
                wire:model.live{{ ($item->config['updateOn'] ?? 'blur') === 'live' ? '' : '.blur' }}="values.{{ $item->id }}"
                class="sr-only"
            />
            <div class="flex items-start justify-between gap-2">
                @if ($option['icon'] ?? null)
                    <span class="text-2xl" aria-hidden="true">{{ $option['icon'] }}</span>
                @endif
                @if (isset($option['price']))
                    <span class="ml-auto rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold whitespace-nowrap text-gray-700 group-has-[:checked]:bg-blue-600 group-has-[:checked]:text-white">
                        {{ $option['price'] > 0 ? '+'.number_format($option['price'], 0, ',', '.').' €' : 'included' }}
                    </span>
                @endif
            </div>
            <span class="font-semibold text-gray-900">{{ $option['label'] }}</span>
            @if ($option['subtitle'] ?? null)
                <span class="text-xs text-gray-500">{{ $option['subtitle'] }}</span>
            @endif
        </label>
    @endforeach
</div>
