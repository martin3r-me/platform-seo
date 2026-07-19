<tr wire:key="url-{{ $url->id }}" class="hover:bg-gray-50 transition-colors">
    <td class="px-4 py-2.5">
        <input type="checkbox" wire:model.live="selectedUrls" value="{{ $url->id }}" class="rounded border-gray-300">
    </td>
    <td class="px-4 py-2.5">
        <div class="flex items-center gap-2">
            @if(!$url->is_own)
                <span class="w-1.5 h-1.5 rounded-full bg-amber-400 flex-shrink-0" title="Wettbewerber"></span>
            @endif
            <a href="{{ route('seo.urls.show', $url) }}" wire:navigate class="text-indigo-600 hover:underline truncate block max-w-xs font-medium">
                {{ ($url->path && $url->path !== '/') ? $url->path : $url->domain }}
            </a>
        </div>
        @if($url->path && $url->path !== '/')
            <div class="text-[10px] text-gray-400 ml-{{ $url->is_own ? '0' : '3.5' }}">{{ $url->domain }}</div>
        @endif
    </td>
    <td class="px-4 py-2.5 text-center">
        @include('seo::partials.url-status-badge', ['status' => $url->status, 'httpStatus' => $url->http_status])
    </td>
    <td class="px-4 py-2.5 text-right text-gray-500 tabular-nums">
        @if($url->child_count > 0)
            <span class="inline-flex items-center gap-0.5 text-[11px]">
                @svg('heroicon-o-document-duplicate', 'w-3 h-3 text-gray-400')
                {{ $url->child_count }}
            </span>
        @else
            <span class="text-gray-300">—</span>
        @endif
    </td>
    <td class="px-4 py-2.5 text-right text-gray-600 tabular-nums">{{ $url->agg_keyword_count }}</td>
    <td class="px-4 py-2.5 text-right">
        @include('seo::partials.sv-badge', ['volume' => $url->agg_search_volume])
    </td>
    <td class="px-4 py-2.5 text-right">
        <span class="font-semibold text-gray-900 tabular-nums">{{ number_format($url->agg_visibility, 1) }}</span>
    </td>
    <td class="px-4 py-2.5 text-right text-gray-600 tabular-nums">{{ $url->agg_backlinks }}</td>
    <td class="px-4 py-2.5 text-right">
        @if($url->onPage && $url->onPage->overall_score !== null)
            @include('seo::partials.score-gauge', ['value' => $url->onPage->overall_score, 'label' => '', 'size' => 'sm'])
        @else
            <span class="text-gray-300">—</span>
        @endif
    </td>
    <td class="px-4 py-2.5 text-right text-[11px] text-gray-400 tabular-nums">
        {{ $url->last_crawled_at?->format('d.m.Y') ?? '—' }}
    </td>
</tr>
