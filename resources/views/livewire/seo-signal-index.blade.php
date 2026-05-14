<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Signale" icon="heroicon-o-bell-alert" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'SEO', 'icon' => 'magnifying-glass-circle', 'route' => 'seo.projects.index'],
            ['label' => $seoProject->name, 'route' => 'seo.projects.show', 'routeParams' => [$seoProject]],
            ['label' => 'Signale'],
        ]" />
    </x-slot>

    <x-ui-page-container>

        {{-- Navigation Tabs --}}
        <div class="flex items-center gap-1 border-b border-gray-100 mb-6">
            <a href="{{ route('seo.projects.show', $seoProject) }}" wire:navigate class="px-4 py-3 text-sm font-medium text-gray-400 hover:text-gray-600">Dashboard</a>
            <a href="{{ route('seo.projects.keywords', $seoProject) }}" wire:navigate class="px-4 py-3 text-sm font-medium text-gray-400 hover:text-gray-600">Keywords</a>
            <a href="{{ route('seo.projects.rankings', $seoProject) }}" wire:navigate class="px-4 py-3 text-sm font-medium text-gray-400 hover:text-gray-600">Rankings</a>
            <a href="{{ route('seo.projects.competitors', $seoProject) }}" wire:navigate class="px-4 py-3 text-sm font-medium text-gray-400 hover:text-gray-600">Wettbewerber</a>
            <a href="{{ route('seo.projects.signals', $seoProject) }}" wire:navigate class="px-4 py-3 text-sm font-medium text-indigo-600 border-b-2 border-indigo-600">Signale</a>
        </div>

        {{-- Status Tabs --}}
        <div class="flex items-center gap-2 mb-6">
            @foreach(['new' => 'Neu', 'acknowledged' => 'Gesehen', 'resolved' => 'Erledigt', '' => 'Alle'] as $status => $label)
                <button wire:click="$set('filterStatus', '{{ $status }}')"
                        class="px-3 py-1.5 text-sm rounded-lg {{ $filterStatus === $status ? 'bg-indigo-50 text-indigo-600 font-medium' : 'text-gray-500 hover:bg-gray-50' }}">
                    {{ $label }}
                    @if(isset($statusCounts[$status]))
                        <span class="ml-1 text-xs text-gray-400">({{ $statusCounts[$status] }})</span>
                    @endif
                </button>
            @endforeach
        </div>

        {{-- Type Filter --}}
        <div class="flex items-center gap-2 mb-6">
            <select wire:model.live="filterType" class="border border-gray-200 rounded-lg px-3 py-2 text-sm">
                <option value="">Alle Typen</option>
                <option value="volume_spike">Volume Spike</option>
                <option value="volume_drop">Volume Drop</option>
                <option value="position_rise">Position Rise</option>
                <option value="position_drop">Position Drop</option>
                <option value="keyword_opportunity">Keyword Opportunity</option>
            </select>
        </div>

        {{-- Signals List --}}
        <div class="space-y-2">
            @forelse($signals as $signal)
                <div wire:key="signal-{{ $signal->id }}" class="bg-white rounded-xl border border-gray-100 p-4">
                    <div class="flex items-start gap-3">
                        <span class="w-2.5 h-2.5 rounded-full flex-shrink-0 mt-1.5
                            @if($signal->severity === 'warning') bg-amber-500
                            @elseif($signal->severity === 'watch') bg-blue-500
                            @elseif($signal->severity === 'critical') bg-red-500
                            @else bg-gray-400
                            @endif"></span>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="font-medium text-sm text-gray-900">{{ $signal->title }}</span>
                                <span class="text-[10px] uppercase tracking-wider px-2 py-0.5 bg-gray-100 rounded text-gray-500">{{ str_replace('_', ' ', $signal->signal_type) }}</span>
                            </div>
                            @if($signal->description)
                                <p class="text-sm text-gray-500 mb-2">{{ $signal->description }}</p>
                            @endif
                            <div class="flex items-center gap-4 text-xs text-gray-400">
                                <span>{{ $signal->detected_at->format('d.m.Y') }}</span>
                                @if($signal->keyword)
                                    <span>Keyword: {{ $signal->keyword->keyword }}</span>
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
