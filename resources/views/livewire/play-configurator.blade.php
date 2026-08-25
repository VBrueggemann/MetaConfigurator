<div class="mx-auto max-w-xl p-6">
    <h1 class="mb-6 text-xl font-semibold text-gray-900">{{ $configurator->name }}</h1>

    @if ($breadcrumbs->isNotEmpty())
        <nav class="mb-6 flex flex-wrap items-center gap-2 text-sm text-gray-600">
            @foreach ($breadcrumbs as $breadcrumb)
                <button
                    type="button"
                    wire:click="goToView('{{ $breadcrumb->id }}')"
                    class="underline hover:text-blue-600"
                >
                    {{ $breadcrumb->config['label'] ?? 'Step '.$loop->iteration }}
                </button>
                @unless ($loop->last)
                    <span aria-hidden="true">&rsaquo;</span>
                @endunless
            @endforeach
        </nav>

        <button
            type="button"
            wire:click="back"
            class="mb-6 text-sm text-gray-600 underline hover:text-blue-600"
        >
            Back
        </button>
    @endif

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
