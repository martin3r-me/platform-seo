<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Wettbewerber" icon="heroicon-o-user-group" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'SEO', 'icon' => 'magnifying-glass-circle', 'route' => 'seo.dashboard'],
            ['label' => 'Wettbewerber'],
        ]" />
    </x-slot>

    <x-slot name="sidebar">
        <livewire:seo.sidebar />
    </x-slot>

    <x-ui-page-container>

        @include('seo::partials.help-banner', ['lens' => 'competitors'])

        <p class="text-[13px] text-gray-500 mb-6">Alle getrackten Wettbewerber-Domains und -URLs deines Teams — team-weit, nicht mehr pro Liste. Klick eine Domain, um die Wettbewerber-URLs zu filtern.</p>

        {{-- Wettbewerber-Domains --}}
        @if($domains->isNotEmpty())
        <div class="flex flex-wrap gap-2 mb-6">
            @foreach($domains as $d)
                <button wire:click="setDomainFilter('{{ $d->domain }}')"
                        class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-[12px] border transition-colors {{ $filterDomain === $d->domain ? 'bg-indigo-50 border-indigo-300 text-indigo-700' : 'bg-white border-gray-200 text-gray-600 hover:bg-gray-50' }}">
                    <span class="font-medium">{{ $d->domain }}</span>
                    <span class="text-[10px] text-gray-400 tabular-nums">{{ $d->url_count }} URLs · {{ number_format($d->keyword_count) }} KW · Sicht. {{ number_format($d->visibility, 0) }}</span>
                </button>
            @endforeach
        </div>
        @endif

        {{-- Wettbewerber-URLs --}}
        <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                <h2 class="text-[13px] font-semibold text-gray-700">Wettbewerber-URLs @if($filterDomain)<span class="text-[11px] font-normal text-gray-400">· {{ $filterDomain }}</span>@endif</h2>
                @if($filterDomain)
                    <button wire:click="setDomainFilter(null)" class="text-[11px] text-indigo-500 hover:underline">Filter zurücksetzen</button>
                @endif
            </div>
            @if($urls->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="w-full text-[12px]">
                    <thead>
                        <tr class="text-left text-[10px] text-gray-400 uppercase tracking-wider border-b border-gray-100 bg-gray-50">
                            <th class="px-4 py-2">URL</th>
                            <th class="px-4 py-2 text-right">Keywords</th>
                            <th class="px-4 py-2 text-right">Suchvolumen</th>
                            <th class="px-4 py-2 text-right">Sichtbarkeit</th>
                            <th class="px-4 py-2 text-right">Backlinks</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($urls as $url)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2">
                                    <a href="{{ route('seo.urls.show', $url->id) }}" wire:navigate class="text-indigo-600 hover:underline truncate max-w-[360px] block">{{ $url->path && $url->path !== '/' ? $url->domain.$url->path : $url->domain }}</a>
                                </td>
                                <td class="px-4 py-2 text-right tabular-nums text-gray-600">{{ number_format($url->keyword_count) }}</td>
                                <td class="px-4 py-2 text-right tabular-nums text-gray-600">{{ number_format($url->total_search_volume) }}</td>
                                <td class="px-4 py-2 text-right tabular-nums font-medium text-gray-700">{{ number_format($url->visibility_score, 0) }}</td>
                                <td class="px-4 py-2 text-right tabular-nums text-gray-600">{{ number_format($url->backlink_count) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
                <div class="p-8 text-center text-[13px] text-gray-400">Keine Wettbewerber-URLs. Sie entstehen automatisch beim Ranking-Abgleich (SERP-Competitors).</div>
            @endif
        </div>

        @if($hasMore)
            <div x-data x-intersect="$wire.loadMore()" class="py-4 text-center">
                <span wire:loading.delay wire:target="loadMore" class="text-[12px] text-gray-400">Laden...</span>
            </div>
        @endif

    </x-ui-page-container>
</x-ui-page>
