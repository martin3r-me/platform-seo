<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Signale" icon="heroicon-o-bell-alert" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'SEO', 'icon' => 'magnifying-glass-circle', 'route' => 'seo.dashboard'],
            ['label' => 'Signale'],
        ]" />
    </x-slot>

    <x-slot name="sidebar">
        @livewire('seo.sidebar', ['active' => 'signals'])
    </x-slot>

    <x-ui-page-container>

        {{-- Filters Row --}}
        <div class="flex items-center gap-4 mb-6 flex-wrap">
            {{-- Status Tabs --}}
            <div class="flex items-center gap-2">
                @foreach(['new' => 'Neu', 'acknowledged' => 'Gesehen', 'resolved' => 'Erledigt', '' => 'Alle'] as $status => $label)
                    <button wire:click="setFilterStatus('{{ $status }}')"
                            class="px-3 py-1.5 text-sm rounded-lg {{ $filterStatus === $status ? 'bg-indigo-50 text-indigo-600 font-medium' : 'text-gray-500 hover:bg-gray-50' }}">
                        {{ $label }}
                        @if($status !== '' && isset($statusCounts[$status]))
                            <span class="ml-1 text-xs text-gray-400">({{ $statusCounts[$status] }})</span>
                        @endif
                    </button>
                @endforeach
            </div>

            {{-- Severity Filter --}}
            <select wire:model.live="filterSeverity" class="border border-gray-200 rounded-lg px-3 py-2 text-sm">
                <option value="">Alle Schweregrade</option>
                <option value="critical">Kritisch</option>
                <option value="warning">Warnung</option>
                <option value="watch">Info</option>
                <option value="opportunity">Chance</option>
            </select>

            {{-- Type Filter --}}
            <select wire:model.live="filterType" class="border border-gray-200 rounded-lg px-3 py-2 text-sm">
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
        <div class="space-y-3">
            @forelse($signals as $signal)
                @php
                    $borderColor = match($signal->severity) {
                        'critical' => 'border-l-red-500',
                        'warning' => 'border-l-amber-500',
                        'watch' => 'border-l-blue-500',
                        'opportunity' => 'border-l-green-500',
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
                <div wire:key="signal-{{ $signal->id }}" class="bg-white rounded-xl border-l-4 {{ $borderColor }} border border-gray-100 p-4">
                    <div class="flex items-start gap-3">
                        <span class="w-2.5 h-2.5 rounded-full flex-shrink-0 mt-1.5 {{ $dotColor }}"></span>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-1 flex-wrap">
                                <span class="font-medium text-sm text-gray-900">{{ $signal->title }}</span>
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
                                <p class="text-sm text-gray-500 mb-2">{{ $signal->description }}</p>
                            @endif
                            <div class="flex items-center gap-4 text-xs text-gray-400 flex-wrap">
                                <span>{{ $signal->detected_at->format('d.m.Y H:i') }}</span>
                                @if($signal->keyword)
                                    <span>Keyword: <span class="text-gray-600">{{ $signal->keyword->keyword }}</span></span>
                                @endif
                                @if($signal->url)
                                    <a href="{{ route('seo.urls.show', $signal->url) }}" wire:navigate class="text-indigo-500 hover:underline truncate max-w-[200px]">
                                        {{ $signal->url->path ?: $signal->url->url }}
                                    </a>
                                @endif
                                @if($signal->metric_delta !== null)
                                    <span class="{{ $signal->metric_delta > 0 ? 'text-green-600' : 'text-red-600' }}">
                                        Delta: {{ $signal->metric_delta > 0 ? '+' : '' }}{{ number_format($signal->metric_delta, 0) }}
                                    </span>
                                @endif
                            </div>
                        </div>
                        <div class="flex items-center gap-1 flex-shrink-0">
                            @if($signal->status === 'new')
                                <button wire:click="acknowledge({{ $signal->id }})" class="px-2 py-1 text-xs text-gray-500 hover:bg-gray-100 rounded" title="Gesehen">
                                    @svg('heroicon-o-eye', 'w-4 h-4')
                                </button>
                            @endif
                            @if($signal->status !== 'resolved')
                                <button wire:click="resolve({{ $signal->id }})" class="px-2 py-1 text-xs text-gray-500 hover:bg-gray-100 rounded" title="Erledigt">
                                    @svg('heroicon-o-check', 'w-4 h-4')
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="py-12 text-center text-gray-400">
                    Keine Signale gefunden.
                </div>
            @endforelse
        </div>

        <div class="mt-4">
            {{ $signals->links() }}
        </div>

    </x-ui-page-container>
</x-ui-page>
