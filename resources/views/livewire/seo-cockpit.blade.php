<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Agentur" icon="heroicon-o-building-office-2" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'SEO', 'icon' => 'magnifying-glass-circle', 'route' => 'seo.dashboard'],
            ['label' => 'Kunden-Portfolio'],
        ]" />
    </x-slot>

    <x-slot name="sidebar">
        <livewire:seo.sidebar />
    </x-slot>

    <x-ui-page-container>

        <div class="mb-6">
            <h1 class="text-lg font-semibold text-gray-900">Kunden-Portfolio</h1>
            <p class="text-[13px] text-gray-500 mt-0.5">{{ $totals['customers'] }} Kunden · {{ $totals['tracked'] }} getrackt · aggregierte Sichtbarkeit <span class="tabular-nums font-medium text-gray-600">{{ number_format($totals['visibility']) }}</span></p>
        </div>

        {{-- Ablage-CTA --}}
        @if($ablageCount > 0)
            <a href="{{ route('seo.perspective.unassigned') }}" wire:navigate
               class="flex items-center justify-between bg-amber-50 border border-amber-200 rounded-xl px-5 py-4 mb-6 hover:bg-amber-100 transition-colors">
                <div class="flex items-center gap-3">
                    @svg('heroicon-o-inbox', 'w-5 h-5 text-amber-600 flex-shrink-0')
                    <div>
                        <div class="text-[13px] font-semibold text-amber-900">{{ $ablageCount }} URLs warten auf Zuordnung</div>
                        <div class="text-[11px] text-amber-700">In der Ablage — einem Kunden zuweisen oder als Wettbewerber klassifizieren</div>
                    </div>
                </div>
                <span class="text-[12px] font-medium text-amber-700 whitespace-nowrap">Zur Ablage →</span>
            </a>
        @endif

        {{-- Kunden-Karten --}}
        @if(!empty($cards))
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($cards as $card)
                    <a href="{{ route('seo.perspective', $card['id']) }}" wire:navigate
                       class="group block bg-white rounded-xl border border-gray-200 p-5 hover:border-indigo-300 hover:shadow-md transition-all">
                        <div class="flex items-start justify-between gap-2 mb-4">
                            <h3 class="font-semibold text-gray-900 text-[15px] truncate group-hover:text-indigo-700 transition-colors">{{ $card['name'] }}</h3>
                            <span class="w-2 h-2 rounded-full mt-1.5 flex-shrink-0 {{ $card['urls'] > 0 ? 'bg-emerald-400' : 'bg-gray-200' }}"></span>
                        </div>

                        @if($card['urls'] > 0)
                            <div class="flex items-baseline gap-1.5 mb-3">
                                <span class="text-3xl font-bold text-gray-900 tabular-nums leading-none">{{ number_format($card['visibility']) }}</span>
                                <span class="text-[10px] text-gray-400 uppercase tracking-wide">Sichtbarkeit</span>
                            </div>
                            <div class="flex items-center gap-4 text-[12px] text-gray-500">
                                <span><strong class="text-gray-700 tabular-nums">{{ number_format($card['urls']) }}</strong> URLs</span>
                                <span><strong class="text-gray-700 tabular-nums">{{ number_format($card['keywords']) }}</strong> Keywords</span>
                                <span><strong class="text-gray-700 tabular-nums">{{ number_format($card['search_volume']) }}</strong> SV</span>
                            </div>
                        @else
                            <div class="py-2 text-[12px] text-gray-400">
                                Noch nicht getrackt — <span class="text-indigo-500 group-hover:underline">URLs aufhängen</span>
                            </div>
                        @endif
                    </a>
                @endforeach
            </div>
        @else
            <div class="py-16 text-center">
                <div class="w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-3">
                    @svg('heroicon-o-building-office-2', 'w-5 h-5 text-gray-400')
                </div>
                <p class="text-sm text-gray-500 font-medium mb-1">Noch keine Kunden</p>
                <p class="text-xs text-gray-400">Sobald im Org-Baum Kunden über die Engagement-Ebene modelliert sind, erscheinen sie hier.</p>
            </div>
        @endif

    </x-ui-page-container>
</x-ui-page>
