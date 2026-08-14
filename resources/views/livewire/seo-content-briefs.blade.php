<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Content-Briefs" icon="heroicon-o-document-text" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'SEO', 'icon' => 'magnifying-glass-circle', 'route' => 'seo.dashboard'],
            ['label' => 'Content-Briefs'],
        ]" />
    </x-slot>

    <x-slot name="sidebar">
        <livewire:seo.sidebar />
    </x-slot>

    <x-ui-page-container>
        <div class="space-y-6">
            {{-- Header --}}
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900">Content-Briefs</h1>
                    <p class="text-[12px] text-gray-500 mt-0.5">Die Arbeitsaufträge der Werkbank — vom Cluster zum Content.</p>
                </div>
                <span class="text-[12px] text-gray-400">{{ $total }} gesamt</span>
            </div>

            {{-- Status-Filter --}}
            <div class="flex items-center gap-1.5 flex-wrap">
                <button wire:click="setStatus('all')"
                        class="text-[12px] px-2.5 py-1 rounded-full border {{ $status === 'all' ? 'bg-gray-900 text-white border-gray-900' : 'bg-white text-gray-600 border-gray-200 hover:border-gray-300' }}">
                    Alle
                </button>
                @foreach($statusCounts as $st => $count)
                    <button wire:click="setStatus('{{ $st }}')"
                            class="text-[12px] px-2.5 py-1 rounded-full border {{ $status === $st ? 'bg-gray-900 text-white border-gray-900' : 'bg-white text-gray-600 border-gray-200 hover:border-gray-300' }}">
                        {{ $st }} <span class="opacity-60">{{ $count }}</span>
                    </button>
                @endforeach
            </div>

            {{-- Liste --}}
            @if($briefs->isNotEmpty())
                <div class="space-y-2">
                    @foreach($briefs as $brief)
                        <a href="{{ route('seo.briefs.show', $brief->id) }}" wire:navigate
                           class="block bg-white rounded-lg border border-gray-200 p-4 hover:border-gray-300 transition-colors">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="text-[10px] uppercase tracking-wider px-2 py-0.5 bg-teal-50 text-teal-700 rounded">{{ $brief->content_type }}</span>
                                        <span class="font-medium text-gray-900 truncate">{{ $brief->name }}</span>
                                        <span class="text-[10px] uppercase tracking-wider px-2 py-0.5 bg-gray-100 text-gray-500 rounded">{{ $brief->status }}</span>
                                    </div>
                                    @if($brief->target_url)
                                        <div class="text-[12px] text-gray-400 mt-1 truncate">{{ $brief->target_url }}</div>
                                    @endif
                                    @if($brief->clusters->isNotEmpty())
                                        <div class="flex items-center gap-1.5 mt-2">
                                            @foreach($brief->clusters as $cluster)
                                                <span class="inline-flex items-center gap-1 text-[11px] text-gray-500">
                                                    <span class="w-2 h-2 rounded-full" style="background: {{ $cluster->color ?: '#94a3b8' }}"></span>
                                                    {{ $cluster->name }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                                <div class="text-right flex-shrink-0 text-[11px] text-gray-400">
                                    <div>{{ $brief->sections_count }} Abschnitte</div>
                                    <div>{{ $brief->notes_count }} Notizen</div>
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>

                @if($hasMore)
                    <div class="text-center">
                        <button wire:click="loadMore" class="text-[12px] text-gray-500 hover:text-gray-700 px-4 py-2">Mehr laden</button>
                    </div>
                @endif
            @else
                <div class="bg-white rounded-lg border border-gray-200 p-8 text-center">
                    <div class="text-[13px] text-gray-500">Noch keine Content-Briefs.</div>
                    <p class="text-[12px] text-gray-400 mt-1">Briefs entstehen aus einem Cluster (seo.content_briefs.POST) oder über ein Content-Signal.</p>
                </div>
            @endif
        </div>
    </x-ui-page-container>
</x-ui-page>
