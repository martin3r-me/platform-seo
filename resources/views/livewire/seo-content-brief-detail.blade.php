<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Content-Brief" icon="heroicon-o-document-text" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'SEO', 'icon' => 'magnifying-glass-circle', 'route' => 'seo.dashboard'],
            ['label' => 'Content-Briefs', 'route' => 'seo.briefs'],
            ['label' => $brief->name],
        ]" />
    </x-slot>

    <x-slot name="sidebar">
        <livewire:seo.sidebar />
    </x-slot>

    <x-ui-page-container>
        <div class="space-y-6" style="max-width: 56rem;">
            {{-- Header --}}
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-[10px] uppercase tracking-wider px-2 py-0.5 bg-teal-50 text-teal-700 rounded">{{ $brief->content_type }}</span>
                    <span class="text-[10px] uppercase tracking-wider px-2 py-0.5 bg-gray-100 text-gray-500 rounded">{{ $brief->status }}</span>
                    @if($brief->search_intent)
                        <span class="text-[10px] uppercase tracking-wider px-2 py-0.5 bg-indigo-50 text-indigo-600 rounded">{{ $brief->search_intent }}</span>
                    @endif
                </div>
                <h1 class="text-xl font-semibold text-gray-900 mt-2">{{ $brief->name }}</h1>
                @if($brief->description)
                    <p class="text-[13px] text-gray-600 mt-1.5 leading-relaxed">{{ $brief->description }}</p>
                @endif
            </div>

            {{-- Meta --}}
            <div class="bg-white rounded-lg border border-gray-200 p-4">
                <dl class="grid grid-cols-4 gap-4 text-[12px]">
                    <div>
                        <dt class="text-gray-400 uppercase tracking-wider text-[10px]">Ziel-URL</dt>
                        <dd class="text-gray-800 mt-0.5 truncate">{{ $brief->target_url ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-400 uppercase tracking-wider text-[10px]">Slug</dt>
                        <dd class="text-gray-800 mt-0.5 truncate">{{ $brief->target_slug ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-400 uppercase tracking-wider text-[10px]">Umfang</dt>
                        <dd class="text-gray-800 mt-0.5">{{ $brief->target_word_count ? number_format($brief->target_word_count, 0, ',', '.') . ' Wörter' : '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-400 uppercase tracking-wider text-[10px]">Cluster</dt>
                        <dd class="mt-0.5">
                            @forelse($clusters as $cluster)
                                <a href="{{ route('seo.clusters.show', $cluster->id) }}" wire:navigate
                                   class="inline-flex items-center gap-1 text-gray-700 hover:text-gray-900">
                                    <span class="w-2 h-2 rounded-full" style="background: {{ $cluster->color ?: '#94a3b8' }}"></span>
                                    {{ $cluster->name }}
                                </a>
                            @empty
                                <span class="text-gray-400">—</span>
                            @endforelse
                        </dd>
                    </div>
                </dl>
            </div>

            {{-- Abschnitte --}}
            <div>
                <h2 class="text-[13px] font-semibold text-gray-700 mb-3">Abschnitte ({{ $sections->count() }})</h2>
                @if($sections->isNotEmpty())
                    <div class="space-y-2">
                        @foreach($sections as $section)
                            <div class="bg-white rounded-lg border border-gray-200 p-4">
                                <div class="flex items-baseline gap-2.5">
                                    <span class="text-[11px] font-mono text-gray-300 flex-shrink-0">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2">
                                            <span class="text-[10px] uppercase tracking-wider text-gray-300 font-mono">{{ $section->heading_level }}</span>
                                            <h3 class="text-[14px] font-semibold text-gray-900">{{ $section->heading }}</h3>
                                        </div>
                                        @if($section->description)
                                            <p class="text-[12px] text-gray-600 mt-1 leading-relaxed">{{ $section->description }}</p>
                                        @endif
                                        @if(!empty($section->target_keywords))
                                            <div class="flex items-center gap-1.5 flex-wrap mt-2">
                                                @foreach($section->target_keywords as $kw)
                                                    <span class="text-[11px] px-2 py-0.5 bg-gray-50 text-gray-600 border border-gray-200 rounded">{{ $kw }}</span>
                                                @endforeach
                                            </div>
                                        @endif
                                        @if($section->notes)
                                            <p class="text-[11px] text-gray-400 mt-2 italic">{{ $section->notes }}</p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-[12px] text-gray-400">Keine Abschnitte.</div>
                @endif
            </div>

            {{-- Notizen --}}
            @if($notes->isNotEmpty())
                <div>
                    <h2 class="text-[13px] font-semibold text-gray-700 mb-3">Notizen</h2>
                    <div class="space-y-2">
                        @foreach($notes as $note)
                            @php
                                $noteStyles = [
                                    'instruction' => ['Instruktion', 'bg-amber-50 text-amber-700 border-amber-100'],
                                    'reference'   => ['Referenz', 'bg-blue-50 text-blue-700 border-blue-100'],
                                    'keyword'     => ['Keywords', 'bg-teal-50 text-teal-700 border-teal-100'],
                                    'competitor'  => ['Wettbewerber', 'bg-rose-50 text-rose-700 border-rose-100'],
                                ];
                                [$label, $cls] = $noteStyles[$note->note_type] ?? [$note->note_type, 'bg-gray-50 text-gray-600 border-gray-200'];
                            @endphp
                            <div class="bg-white rounded-lg border border-gray-200 p-4">
                                <span class="text-[10px] uppercase tracking-wider px-2 py-0.5 border rounded {{ $cls }}">{{ $label }}</span>
                                <p class="text-[12px] text-gray-700 mt-2 leading-relaxed" style="white-space: pre-line;">{{ $note->content }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </x-ui-page-container>
</x-ui-page>
