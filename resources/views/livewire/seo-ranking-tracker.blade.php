<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Rankings" icon="heroicon-o-chart-bar" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'SEO', 'icon' => 'magnifying-glass-circle', 'route' => 'seo.projects.index'],
            ['label' => $seoProject->name, 'route' => 'seo.projects.show', 'routeParams' => [$seoProject]],
            ['label' => 'Rankings'],
        ]" />
    </x-slot>

    <x-ui-page-container>

        {{-- Navigation Tabs --}}
        <div class="flex items-center gap-1 border-b border-gray-100 mb-6">
            <a href="{{ route('seo.projects.show', $seoProject) }}" wire:navigate class="px-4 py-3 text-sm font-medium text-gray-400 hover:text-gray-600">Dashboard</a>
            <a href="{{ route('seo.projects.keywords', $seoProject) }}" wire:navigate class="px-4 py-3 text-sm font-medium text-gray-400 hover:text-gray-600">Keywords</a>
            <a href="{{ route('seo.projects.rankings', $seoProject) }}" wire:navigate class="px-4 py-3 text-sm font-medium text-indigo-600 border-b-2 border-indigo-600">Rankings</a>
            <a href="{{ route('seo.projects.competitors', $seoProject) }}" wire:navigate class="px-4 py-3 text-sm font-medium text-gray-400 hover:text-gray-600">Wettbewerber</a>
            <a href="{{ route('seo.projects.signals', $seoProject) }}" wire:navigate class="px-4 py-3 text-sm font-medium text-gray-400 hover:text-gray-600">Signale</a>
        </div>

        {{-- Period Selector --}}
        <div class="flex items-center gap-2 mb-6">
            @foreach([7 => '7 Tage', 14 => '14 Tage', 30 => '30 Tage', 90 => '90 Tage'] as $days => $label)
                <button wire:click="setPeriod({{ $days }})"
                        class="px-3 py-1.5 text-sm rounded-lg {{ $periodDays === $days ? 'bg-indigo-50 text-indigo-600 font-medium' : 'text-gray-500 hover:bg-gray-50' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        {{-- Summary --}}
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
            <div class="bg-white rounded-xl border border-gray-100 p-4 text-center">
                <div class="text-2xl font-light text-green-600">{{ $trends['summary']['rising_count'] }}</div>
                <div class="text-xs text-gray-400 mt-1">Aufsteiger</div>
            </div>
            <div class="bg-white rounded-xl border border-gray-100 p-4 text-center">
                <div class="text-2xl font-light text-red-600">{{ $trends['summary']['falling_count'] }}</div>
                <div class="text-xs text-gray-400 mt-1">Absteiger</div>
            </div>
            <div class="bg-white rounded-xl border border-gray-100 p-4 text-center">
                <div class="text-2xl font-light text-gray-600">{{ $trends['summary']['stable_count'] }}</div>
                <div class="text-xs text-gray-400 mt-1">Stabil</div>
            </div>
            <div class="bg-white rounded-xl border border-gray-100 p-4 text-center">
                <div class="text-2xl font-light text-blue-600">{{ $trends['summary']['new_entries_count'] }}</div>
                <div class="text-xs text-gray-400 mt-1">Neu</div>
            </div>
            <div class="bg-white rounded-xl border border-gray-100 p-4 text-center">
                <div class="text-2xl font-light text-gray-400">{{ $trends['summary']['no_data_count'] }}</div>
                <div class="text-xs text-gray-400 mt-1">Keine Daten</div>
            </div>
        </div>

        {{-- Rising Keywords --}}
        @if(!empty($trends['rising']))
            <div class="mb-8">
                <h3 class="text-sm font-medium text-gray-700 mb-3 flex items-center gap-2">
                    @svg('heroicon-o-arrow-trending-up', 'w-4 h-4 text-green-500')
                    Aufsteiger
                </h3>
                <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 text-left text-gray-400">
                                <th class="px-4 py-3">Keyword</th>
                                <th class="px-4 py-3">Cluster</th>
                                <th class="px-4 py-3 text-right">Position</th>
                                <th class="px-4 py-3 text-right">Ver&auml;nderung</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($trends['rising'] as $entry)
                                <tr class="border-b border-gray-50">
                                    <td class="px-4 py-2.5 font-medium text-gray-900">{{ $entry['keyword'] }}</td>
                                    <td class="px-4 py-2.5 text-gray-500 text-xs">{{ $entry['cluster'] ?? '—' }}</td>
                                    <td class="px-4 py-2.5 text-right text-gray-700">{{ $entry['current_position'] }}</td>
                                    <td class="px-4 py-2.5 text-right text-green-600 font-medium">+{{ $entry['position_change'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- Falling Keywords --}}
        @if(!empty($trends['falling']))
            <div class="mb-8">
                <h3 class="text-sm font-medium text-gray-700 mb-3 flex items-center gap-2">
                    @svg('heroicon-o-arrow-trending-down', 'w-4 h-4 text-red-500')
                    Absteiger
                </h3>
                <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 text-left text-gray-400">
                                <th class="px-4 py-3">Keyword</th>
                                <th class="px-4 py-3">Cluster</th>
                                <th class="px-4 py-3 text-right">Position</th>
                                <th class="px-4 py-3 text-right">Ver&auml;nderung</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($trends['falling'] as $entry)
                                <tr class="border-b border-gray-50">
                                    <td class="px-4 py-2.5 font-medium text-gray-900">{{ $entry['keyword'] }}</td>
                                    <td class="px-4 py-2.5 text-gray-500 text-xs">{{ $entry['cluster'] ?? '—' }}</td>
                                    <td class="px-4 py-2.5 text-right text-gray-700">{{ $entry['current_position'] }}</td>
                                    <td class="px-4 py-2.5 text-right text-red-600 font-medium">{{ $entry['position_change'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

    </x-ui-page-container>
</x-ui-page>
