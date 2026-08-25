<div class="mx-auto max-w-xl p-6">
    <h1 class="mb-6 text-xl font-semibold text-gray-900">{{ $configurator->name }}</h1>

    <div class="space-y-6">
        @foreach ($items as $item)
            @if (in_array($item->id, $visibleViewIds, true))
                @include('livewire.play-configurator.item', ['item' => $item])
            @endif
        @endforeach
    </div>

    @php
        $flattenIds = function ($nodes) use (&$flattenIds) {
            return collect($nodes)->flatMap(fn ($node) => collect([$node->id])->merge($flattenIds($node->children)));
        };
        $unattachedErrors = collect($fieldErrors)->except($flattenIds($items));
    @endphp
    @if ($unattachedErrors->isNotEmpty())
        <ul class="mt-4 text-sm text-red-600">
            @foreach ($fieldErrors[$item->id] as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    @endif

    <button
        type="button"
        wire:click="next"
        class="mt-6 rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700"
    >
        Next
    </button>
</div>
