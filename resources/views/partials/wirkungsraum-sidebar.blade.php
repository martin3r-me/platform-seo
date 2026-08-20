{{-- Geteilte innere Wirkungsraum-Navigation (route-basiert) — genutzt von der
     Detail-Seite und vom Posteingang-Route. Fundament der Routen-Umstellung:
     Stationen adressierbar via ?view=, der Posteingang als eigene Route.
     Erwartet: $portfolio, $active (Schlüssel der aktiven Sicht), $health. --}}
@php
    $navBtn = fn ($isActive) => 'w-full flex items-center gap-2 px-2 py-1.5 rounded text-[13px] transition-colors '
        .($isActive ? 'bg-gray-100 font-medium text-gray-900' : 'text-gray-600 hover:bg-gray-50');
@endphp
<x-ui-page-sidebar title="Wirkungsraum" icon="heroicon-o-rocket-launch" width="w-64" storeKey="sidebarOpen">
    <div class="p-3 space-y-5">
        {{-- Überblick + der Posteingang (die Zentrale) direkt dahinter --}}
        <div>
            <h3 class="text-[10px] font-semibold uppercase tracking-wider text-gray-400 mb-1.5 px-2">Überblick</h3>
            <a href="{{ route('seo.portfolios.show', $portfolio) }}" wire:navigate class="{{ $navBtn($active === 'dashboard') }}">
                <span class="flex-1 text-left">Dashboard</span>
            </a>
            <a href="{{ route('seo.portfolios.inbox', $portfolio) }}" wire:navigate class="{{ $navBtn($active === 'inbox') }}">
                <span class="flex-1 text-left">Posteingang <span class="text-gray-400 font-normal">· Zentrale</span></span>
                @if(($health['inbox_count'] ?? 0) > 0)
                    <span class="text-[10px] tabular-nums px-1.5 py-0.5 rounded-full bg-[color:var(--nx-info)] text-white">{{ $health['inbox_count'] }}</span>
                @endif
            </a>
        </div>

        {{-- Stationen — der rote Faden --}}
        <div>
            <h3 class="text-[10px] font-semibold uppercase tracking-wider text-gray-400 mb-0.5 px-2">Stationen <span class="text-gray-300 normal-case font-normal">· der rote Faden</span></h3>
            <p class="px-2 mb-1.5 text-[9px] text-gray-400 leading-tight">Meta → Daten → Ordnen → Verteilen → Wirkung</p>
            <a href="{{ route('seo.portfolios.show', ['seoPortfolio' => $portfolio, 'view' => 'meta']) }}" wire:navigate class="{{ $navBtn($active === 'meta') }}">
                <span class="text-[10px] text-gray-400 w-3">◈</span>
                <span class="flex-1 text-left">Meta <span class="text-gray-400 font-normal">· Steckbrief</span></span>
            </a>
            @foreach(['messen' => 'Daten', 'ordnen' => 'Ordnen', 'verteilen' => 'Verteilen', 'konvertieren' => 'Wirkung'] as $stKey => $stLabel)
                <a href="{{ route('seo.portfolios.show', ['seoPortfolio' => $portfolio, 'view' => $stKey]) }}" wire:navigate class="{{ $navBtn($active === $stKey) }}">
                    <span class="text-[10px] tabular-nums text-gray-400 w-3">{{ $loop->iteration }}</span>
                    <span class="flex-1 text-left">{{ $stLabel }}</span>
                    @if(($health['current'] ?? null) === $stKey)
                        <span class="w-1.5 h-1.5 rounded-full shrink-0" style="background:#0f766e" title="Aktuelles Gate"></span>
                    @endif
                </a>
            @endforeach
        </div>

        {{-- Bestand --}}
        <div>
            <h3 class="text-[10px] font-semibold uppercase tracking-wider text-gray-400 mb-1.5 px-2">Bestand</h3>
            @foreach(['entities' => 'Entitäten', 'keywords' => 'Keywords', 'clusters' => 'Cluster', 'competitors' => 'Wettbewerber'] as $bKey => $bLabel)
                <a href="{{ route('seo.portfolios.show', ['seoPortfolio' => $portfolio, 'view' => $bKey]) }}" wire:navigate class="{{ $navBtn($active === $bKey) }}">
                    <span class="flex-1 text-left">{{ $bLabel }}</span>
                </a>
            @endforeach
        </div>
    </div>
</x-ui-page-sidebar>
