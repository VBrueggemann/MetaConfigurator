<div class="sticky top-2 z-10 flex items-center justify-between gap-3 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 shadow-sm">
    <span class="text-sm font-medium text-blue-900">{{ $item->config['label'] ?? '' }}</span>
    <span class="text-lg font-bold text-blue-900">{!! $item->config['content'] ?? '' !!}</span>
</div>
