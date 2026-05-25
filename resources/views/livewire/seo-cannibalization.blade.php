<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Kannibalisierung" icon="heroicon-o-exclamation-triangle" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'SEO', 'icon' => 'magnifying-glass-circle', 'route' => 'seo.dashboard'],
            ['label' => 'Listen', 'route' => 'seo.lists'],
            ['label' => $seoUrlList->name, 'href' => route('seo.lists.show', $seoUrlList)],
            ['label' => 'Kannibalisierung'],
        ]" />
    </x-slot>

    <x-slot name="sidebar">
        @include('seo::partials.sidebar', ['active' => 'lists'])
    </x-slot>

    <x-ui-page-container>

        {{-- Sub-Navigation --}}
        <div class="flex items-center gap-1 border-b border-gray-200 mb-6">
            <a href="{{ route('seo.lists.show', $seoUrlList) }}" wire:navigate class="px-4 py-3 text-[13px] font-medium text-gray-500 hover:text-gray-700 transition-colors">Übersicht</a>
            <a href="{{ route('seo.lists.competitors', $seoUrlList) }}" wire:navigate class="px-4 py-3 text-[13px] font-medium text-gray-500 hover:text-gray-700 transition-colors">Wettbewerber</a>
            <a href="{{ route('seo.lists.cannibalization', $seoUrlList) }}" wire:navigate class="px-4 py-3 text-[13px] font-medium text-[#166EE1] border-b-2 border-[#166EE1]">Kannibalisierung</a>
            <a href="{{ route('seo.lists.signals', $seoUrlList) }}" wire:navigate class="px-4 py-3 text-[13px] font-medium text-gray-500 hover:text-gray-700 transition-colors">Signale</a>
        </div>

        {{-- Intro --}}
        <p class="text-[13px] text-gray-500 mb-6">Keyword-Kannibalisierung tritt auf, wenn mehrere deiner Seiten für dasselbe Keyword ranken und sich gegenseitig Sichtbarkeit stehlen. Google weiß dann nicht, welche Seite es bevorzugen soll. Konsolidiere Inhalte oder setze Canonical-Tags, um das zu lösen.</p>

        {{-- Summary --}}
        <div class="grid grid-cols-2 gap-4 mb-8">
            <div class="bg-white rounded-lg border border-gray-200 p-4">
                <div class="flex items-center gap-2 mb-1">
                    @svg('heroicon-o-exclamation-triangle', 'w-4 h-4 text-amber-500')
                    <span class="text-[11px] font-medium text-gray-400 uppercase tracking-wide">Betroffene Keywords</span>
                </div>
                <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ count($cannibalization) }}</div>
                <div class="text-[10px] text-gray-400 mt-1">Keywords mit mehrfach rankenden URLs</div>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 p-4">
                <div class="flex items-center gap-2 mb-1">
                    @svg('heroicon-o-globe-alt', 'w-4 h-4 text-red-500')
                    <span class="text-[11px] font-medium text-gray-400 uppercase tracking-wide">Betroffene URLs</span>
                </div>
                <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ collect($cannibalization)->flatMap(fn($c) => collect($c['urls'])->pluck('url'))->unique()->count() }}</div>
                <div class="text-[10px] text-gray-400 mt-1">Seiten, die sich gegenseitig konkurrieren</div>
            </div>
        </div>

        {{-- Cannibalization Cards --}}
        @if(!empty($cannibalization))
            <div class="space-y-3">
                @foreach($cannibalization as $item)
                    @php
                        $urlCount = count($item['urls']);
                        $positions = array_column($item['urls'], 'position');
                        $positionSpread = count($positions) >= 2 ? max($positions) - min($positions) : 0;
                        $severity = $urlCount > 2 ? 'red' : ($positionSpread < 5 ? 'red' : 'amber');
                    @endphp
                    <div class="bg-white rounded-lg border-l-4 {{ $severity === 'red' ? 'border-l-red-400' : 'border-l-amber-400' }} border border-gray-200 overflow-hidden">
                        <div class="px-5 py-3 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <span class="font-semibold text-[13px] text-gray-900">{{ $item['keyword'] }}</span>
                                @include('seo::partials.sv-badge', ['volume' => $item['search_volume']])
                                <span class="text-[11px] text-gray-400">{{ $urlCount }} URLs ranken</span>
                            </div>
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold uppercase tracking-wider {{ $severity === 'red' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700' }}">
                                {{ $severity === 'red' ? 'Kritisch' : 'Warnung' }}
                            </span>
                        </div>
                        <table class="w-full text-[13px]">
                            <tbody class="divide-y divide-gray-100">
                                @foreach($item['urls'] as $urlData)
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="px-5 py-2 text-indigo-600 truncate max-w-lg text-[12px]">{{ $urlData['url'] }}</td>
                                        <td class="px-4 py-2 text-right w-20">
                                            @include('seo::partials.position-badge', ['position' => $urlData['position'], 'change' => null])
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endforeach
            </div>
        @else
            <div class="py-16 text-center">
                <div class="w-12 h-12 rounded-full bg-green-50 flex items-center justify-center mx-auto mb-3">
                    @svg('heroicon-o-check-circle', 'w-6 h-6 text-green-500')
                </div>
                <p class="text-sm text-gray-500 font-medium mb-1">Keine Kannibalisierung gefunden</p>
                <p class="text-xs text-gray-400">Alle Keywords haben eindeutige Landing Pages. Das ist gut — so kann Google jede Seite klar zuordnen.</p>
            </div>
        @endif

    </x-ui-page-container>
</x-ui-page>
