<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Signale" icon="heroicon-o-bell-alert" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'SEO', 'icon' => 'magnifying-glass-circle', 'route' => 'seo.dashboard'],
            ['label' => 'Listen', 'route' => 'seo.lists'],
            ['label' => $seoUrlList->name, 'href' => route('seo.lists.show', $seoUrlList)],
            ['label' => 'Signale'],
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
            <a href="{{ route('seo.lists.cannibalization', $seoUrlList) }}" wire:navigate class="px-4 py-3 text-[13px] font-medium text-gray-500 hover:text-gray-700 transition-colors">Kannibalisierung</a>
            <a href="{{ route('seo.lists.signals', $seoUrlList) }}" wire:navigate class="px-4 py-3 text-[13px] font-medium text-[#166EE1] border-b-2 border-[#166EE1]">Signale</a>
        </div>

        {{-- Intro --}}
        <p class="text-[13px] text-gray-500 mb-6">Automatisch erkannte SEO-Veränderungen für diese URL-Liste. Signale werden täglich generiert, wenn Positionen, Suchvolumen oder URL-Status sich signifikant ändern. So verpasst du keine wichtigen Entwicklungen.</p>

        {{-- Filters Row --}}
        <div class="flex items-center gap-3 mb-6 flex-wrap">
            {{-- Status Tabs --}}
            <div class="flex items-center gap-1 bg-gray-100 rounded-lg p-0.5">
                @foreach(['new' => 'Neu', 'acknowledged' => 'Gesehen', 'resolved' => 'Erledigt', '' => 'Alle'] as $status => $label)
                    <button wire:click="setFilterStatus('{{ $status }}')"
                            class="px-3 py-1.5 text-[12px] rounded-md transition-colors {{ $filterStatus === $status ? 'bg-white text-gray-900 font-medium shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                        {{ $label }}
                        @if($status !== '' && isset($statusCounts[$status]))
                            <span class="ml-0.5 text-[10px] {{ $filterStatus === $status ? 'text-gray-500' : 'text-gray-400' }}">({{ $statusCounts[$status] }})</span>
                        @endif
                    </button>
                @endforeach
            </div>

            <select wire:model.live="filterSeverity" class="border border-gray-200 rounded-lg px-3 py-2 text-[12px] bg-white">
                <option value="">Alle Schweregrade</option>
                <option value="critical">Kritisch</option>
                <option value="warning">Warnung</option>
                <option value="watch">Info</option>
                <option value="opportunity">Chance</option>
            </select>

            <select wire:model.live="filterType" class="border border-gray-200 rounded-lg px-3 py-2 text-[12px] bg-white">
                <option value="">Alle Typen</option>
                <option value="volume_spike">Volume Spike</option>
                <option value="volume_drop">Volume Drop</option>
                <option value="position_rise">Position Rise</option>
                <option value="position_drop">Position Drop</option>
                <option value="keyword_opportunity">Keyword Opportunity</option>
                <option value="redirect_detected">Redirect</option>
                <option value="url_error">URL Fehler</option>
                <option value="cannibalization">Kannibalisierung</option>
            </select>
        </div>

        {{-- Signals List --}}
        <div class="space-y-2">
            @forelse($signals as $signal)
                @php
                    $borderColor = match($signal->severity) {
                        'critical' => 'border-l-red-400',
                        'warning' => 'border-l-amber-400',
                        'watch' => 'border-l-blue-400',
                        'opportunity' => 'border-l-green-400',
                        default => 'border-l-gray-300',
                    };
                    $dotColor = match($signal->severity) {
                        'critical' => 'bg-red-500',
                        'warning' => 'bg-amber-500',
                        'watch' => 'bg-blue-500',
                        'opportunity' => 'bg-green-500',
                        default => 'bg-gray-400',
                    };
                    $severityLabel = match($signal->severity) {
                        'critical' => 'Kritisch',
                        'warning' => 'Warnung',
                        'watch' => 'Info',
                        'opportunity' => 'Chance',
                        default => $signal->severity,
                    };
                @endphp
                <div wire:key="signal-{{ $signal->id }}" class="bg-white rounded-lg border-l-4 {{ $borderColor }} border border-gray-200 p-4">
                    <div class="flex items-start gap-3">
                        <span class="w-2 h-2 rounded-full flex-shrink-0 mt-1.5 {{ $dotColor }}"></span>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-1 flex-wrap">
                                <span class="font-medium text-[13px] text-gray-900">{{ $signal->title }}</span>
                                <span class="text-[10px] uppercase tracking-wider px-2 py-0.5 bg-gray-100 rounded text-gray-500">{{ str_replace('_', ' ', $signal->signal_type) }}</span>
                                <span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded {{ match($signal->severity) {
                                    'critical' => 'bg-red-100 text-red-600',
                                    'warning' => 'bg-amber-100 text-amber-600',
                                    'watch' => 'bg-blue-100 text-blue-600',
                                    'opportunity' => 'bg-green-100 text-green-600',
                                    default => 'bg-gray-100 text-gray-500',
                                } }}">{{ $severityLabel }}</span>
                            </div>
                            @if($signal->description)
                                <p class="text-[12px] text-gray-500 mb-2">{{ $signal->description }}</p>
                            @endif
                            <div class="flex items-center gap-3 text-[11px] text-gray-400 flex-wrap">
                                <span>{{ $signal->detected_at->format('d.m.Y H:i') }}</span>
                                @if($signal->keyword)
                                    <span class="flex items-center gap-1">
                                        @svg('heroicon-o-key', 'w-3 h-3')
                                        <span class="text-gray-600">{{ $signal->keyword->keyword }}</span>
                                    </span>
                                @endif
                                @if($signal->url)
                                    <a href="{{ route('seo.urls.show', $signal->url) }}" wire:navigate class="text-indigo-500 hover:underline truncate max-w-[200px]">
                                        {{ $signal->url->path ?: $signal->url->url }}
                                    </a>
                                @endif
                                @if($signal->metric_delta !== null)
                                    <span class="{{ $signal->metric_delta > 0 ? 'text-green-600' : 'text-red-600' }} font-medium">
                                        {{ $signal->metric_delta > 0 ? '+' : '' }}{{ number_format($signal->metric_delta, 0) }}
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
                        @svg('heroicon-o-bell-alert', 'w-5 h-5 text-gray-400')
                    </div>
                    <p class="text-sm text-gray-500 font-medium mb-1">Keine Signale</p>
                    <p class="text-xs text-gray-400">Es wurden keine SEO-Veränderungen für diese Liste erkannt. Signale erscheinen automatisch, sobald sich Rankings oder Traffic ändern.</p>
                </div>
            @endforelse
        </div>

        @if($signals->hasPages())
            <div class="mt-4">{{ $signals->links() }}</div>
        @endif

    </x-ui-page-container>
</x-ui-page>
