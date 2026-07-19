<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Empfehlungen" icon="heroicon-o-light-bulb" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'SEO', 'icon' => 'magnifying-glass-circle', 'route' => 'seo.dashboard'],
            ['label' => 'Empfehlungen'],
        ]" />
    </x-slot>

    <x-slot name="sidebar">
        <livewire:seo.sidebar />
    </x-slot>

    <x-ui-page-container>

        @include('seo::partials.help-banner', ['lens' => 'recommendations'])

        {{-- Intro --}}
        <p class="text-[13px] text-gray-500 mb-6">Konkrete Handlungsempfehlungen, automatisch aus den konsolidierten SEO-Daten abgeleitet — mit Datenbeleg. Erledige oder markiere sie als gesehen; offene Empfehlungen fließen an den Organisations-Knoten und ins Kundenportal.</p>

        {{-- Filters Row --}}
        <div class="flex items-center gap-3 mb-6 flex-wrap">
            {{-- Status Tabs --}}
            <div class="flex items-center gap-1 bg-gray-100 rounded-lg p-0.5">
                @foreach(['open' => 'Offen', 'resolved' => 'Erledigt', 'all' => 'Alle'] as $status => $label)
                    <button wire:click="setFilterStatus('{{ $status }}')"
                            class="px-3 py-1.5 text-[12px] rounded-md transition-colors {{ $filterStatus === $status ? 'bg-white text-gray-900 font-medium shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                        {{ $label }}
                        @if(isset($statusCounts[$status]))
                            <span class="ml-0.5 text-[10px] {{ $filterStatus === $status ? 'text-gray-500' : 'text-gray-400' }}">({{ $statusCounts[$status] }})</span>
                        @endif
                    </button>
                @endforeach
            </div>

            <select wire:model.live="filterType" class="border border-gray-200 rounded-lg px-3 py-2 text-[12px] bg-white">
                <option value="">Alle Typen</option>
                @foreach($typeMeta as $type => $meta)
                    <option value="{{ $type }}">{{ $meta['label'] }}@if(isset($typeCounts[$type])) ({{ $typeCounts[$type] }})@endif</option>
                @endforeach
            </select>
        </div>

        {{-- Recommendations List --}}
        <div class="space-y-2">
            @forelse($signals as $signal)
                @php
                    $meta = $typeMeta[$signal->signal_type] ?? ['label' => str_replace('_', ' ', $signal->signal_type), 'icon' => 'heroicon-o-light-bulb'];
                    $borderColor = match($signal->severity) {
                        'warning' => 'border-l-amber-400',
                        'watch' => 'border-l-blue-400',
                        'info' => 'border-l-gray-300',
                        default => 'border-l-gray-300',
                    };
                    $ctx = $signal->context ?? [];
                    $chips = [];
                    if (!empty($ctx['keyword'])) { $chips[] = $ctx['keyword']; }
                    if (isset($ctx['volume'])) { $chips[] = number_format($ctx['volume']) . ' Vol.'; }
                    if (isset($ctx['position'])) { $chips[] = 'Pos. ' . $ctx['position']; }
                    if (isset($ctx['difficulty'])) { $chips[] = 'KD ' . $ctx['difficulty']; }
                    if (isset($ctx['backlinks'])) { $chips[] = $ctx['backlinks'] . ' Backlinks'; }
                    if (isset($ctx['coverage_pct'])) { $chips[] = $ctx['coverage_pct'] . '% Abdeckung'; }
                    if (isset($ctx['keyword_count'])) { $chips[] = $ctx['keyword_count'] . ' Keywords'; }
                @endphp
                <div wire:key="rec-{{ $signal->id }}" class="bg-white rounded-lg border-l-4 {{ $borderColor }} border border-gray-200 p-4">
                    <div class="flex items-start gap-3">
                        <span class="flex-shrink-0 mt-0.5 text-gray-400">@svg($meta['icon'], 'w-5 h-5')</span>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-1 flex-wrap">
                                <span class="text-[10px] uppercase tracking-wider px-2 py-0.5 bg-indigo-50 text-indigo-600 rounded font-medium">{{ $meta['label'] }}</span>
                                <span class="font-medium text-[13px] text-gray-900">{{ $signal->title }}</span>
                                @if($signal->status === 'acknowledged')
                                    <span class="text-[10px] uppercase tracking-wider px-2 py-0.5 bg-blue-50 text-blue-500 rounded">Gesehen</span>
                                @elseif($signal->status === 'resolved')
                                    <span class="text-[10px] uppercase tracking-wider px-2 py-0.5 bg-green-50 text-green-600 rounded">Erledigt</span>
                                @endif
                            </div>
                            @if($signal->description)
                                <p class="text-[12px] text-gray-500 mb-2">{{ $signal->description }}</p>
                            @endif
                            <div class="flex items-center gap-2 flex-wrap">
                                @foreach($chips as $chip)
                                    <span class="text-[11px] px-2 py-0.5 bg-gray-50 border border-gray-200 rounded text-gray-600">{{ $chip }}</span>
                                @endforeach
                            </div>
                            <div class="flex items-center gap-3 text-[11px] text-gray-400 flex-wrap mt-2">
                                <span>{{ $signal->detected_at?->format('d.m.Y') }}</span>
                                @if($signal->url)
                                    <a href="{{ route('seo.urls.show', $signal->url) }}" wire:navigate class="text-indigo-500 hover:underline truncate max-w-[240px]">
                                        {{ $signal->url->path ?: $signal->url->url }}
                                    </a>
                                @elseif(!empty($ctx['cluster']))
                                    <span class="flex items-center gap-1">
                                        @svg('heroicon-o-squares-2x2', 'w-3 h-3')
                                        <span class="text-gray-600">{{ $ctx['cluster'] }}</span>
                                    </span>
                                @endif
                            </div>
                        </div>
                        <div class="flex items-center gap-1 flex-shrink-0">
                            @if($signal->status === 'new')
                                <button wire:click="acknowledge({{ $signal->id }})" class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-md transition" title="Als gesehen markieren">
                                    @svg('heroicon-o-eye', 'w-4 h-4')
                                </button>
                            @endif
                            @if($signal->status !== 'resolved')
                                <button wire:click="resolve({{ $signal->id }})" class="p-1.5 text-gray-400 hover:text-green-600 hover:bg-green-50 rounded-md transition" title="Als erledigt markieren">
                                    @svg('heroicon-o-check', 'w-4 h-4')
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="py-16 text-center">
                    <div class="w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-3">
                        @svg('heroicon-o-light-bulb', 'w-5 h-5 text-gray-400')
                    </div>
                    <p class="text-sm text-gray-500 font-medium mb-1">Keine Empfehlungen</p>
                    <p class="text-xs text-gray-400">Sobald genug Daten vorliegen, leitet die Engine hier konkrete Handlungen ab (URL ausbauen, Backlinks, neue URL, Quick Wins …).</p>
                </div>
            @endforelse
        </div>

        @if($hasMore)
            <div x-data x-intersect="$wire.loadMore()" class="py-4 text-center">
                <span wire:loading.delay wire:target="loadMore" class="text-[12px] text-gray-400">Laden...</span>
            </div>
        @endif

    </x-ui-page-container>
</x-ui-page>
