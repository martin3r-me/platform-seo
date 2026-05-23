<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Kannibalisierung" icon="heroicon-o-exclamation-triangle" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'SEO', 'icon' => 'magnifying-glass-circle', 'route' => 'seo.dashboard'],
            ['label' => 'Kannibalisierung'],
        ]" />
    </x-slot>

    <x-ui-page-container>

        @include('seo::partials.project-tabs', ['active' => 'cannibalization'])

        {{-- Summary --}}
        <x-ui-stats-grid :cols="2">
            <x-ui-dashboard-tile title="Kannibalisierte Keywords" :count="count($cannibalization)" icon="exclamation-triangle" variant="warning" />
            <x-ui-dashboard-tile title="Betroffene URLs" :count="collect($cannibalization)->flatMap(fn($c) => collect($c['urls'])->pluck('url'))->unique()->count()" icon="globe-alt" variant="danger" />
        </x-ui-stats-grid>

        {{-- Cannibalization Table --}}
        @if(!empty($cannibalization))
            <div class="space-y-4">
                @foreach($cannibalization as $item)
                    @php
                        $urlCount = count($item['urls']);
                        $positions = array_column($item['urls'], 'position');
                        $positionSpread = count($positions) >= 2 ? max($positions) - min($positions) : 0;
                        $severity = $urlCount > 2 ? 'red' : ($positionSpread < 5 ? 'red' : 'amber');
                    @endphp
                    <div class="bg-white rounded-xl border-l-4 {{ $severity === 'red' ? 'border-l-red-500' : 'border-l-amber-500' }} border border-gray-100 overflow-hidden">
                        <div class="px-6 py-4 flex items-center justify-between">
                            <div class="flex items-center gap-4">
                                <span class="font-medium text-gray-900">{{ $item['keyword'] }}</span>
                                @include('seo::partials.sv-badge', ['volume' => $item['search_volume']])
                                <span class="text-xs text-gray-400">{{ $urlCount }} URLs ranken</span>
                            </div>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $severity === 'red' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700' }}">
                                {{ $severity === 'red' ? 'Kritisch' : 'Warnung' }}
                            </span>
                        </div>
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-t border-gray-100 text-left text-gray-400">
                                    <th class="px-6 py-2">URL</th>
                                    <th class="px-4 py-2 text-right">Position</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($item['urls'] as $urlData)
                                    <tr class="border-t border-gray-50">
                                        <td class="px-6 py-2 text-indigo-600 truncate max-w-lg">{{ $urlData['url'] }}</td>
                                        <td class="px-4 py-2 text-right">
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
            <div class="py-12 text-center text-gray-400">
                Keine Keyword-Kannibalisierungen gefunden.
            </div>
        @endif

    </x-ui-page-container>
</x-ui-page>
