@props(['url'])

@php
    $labels = [
        'keyword_metrics' => 'Keyword-Metriken',
        'gsc' => 'Search Console',
        'serp_ranking' => 'SERP-Rankings',
        'backlinks' => 'Backlinks',
        'on_page' => 'On-Page',
        'plausible' => 'Traffic',
        'llm_mentions' => 'LLM-Erwähnungen',
    ];
    $freshness = $url->collectorFreshness();
@endphp

@if(!empty($freshness))
    <div class="bg-white rounded-lg border border-gray-200 p-4">
        <div class="flex items-center justify-between mb-3">
            <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide">Datenaktualität</div>
            <div class="text-[11px]">
                @include('seo::partials.freshness-badge', ['url' => $url, 'showNext' => true])
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            @foreach($freshness as $key => $info)
                @php
                    $last = $info['last'];
                    $due = $info['due'];
                    $state = ! $last ? 'never'
                        : ($info['overdue'] ? 'overdue'
                        : ($due->lte(now()->addDay()) ? 'due_soon' : 'fresh'));
                    $c = match($state) {
                        'fresh'    => ['dot' => 'bg-green-500', 'chip' => 'border-gray-200 bg-white'],
                        'due_soon' => ['dot' => 'bg-amber-400', 'chip' => 'border-amber-200 bg-amber-50'],
                        'overdue'  => ['dot' => 'bg-red-500',   'chip' => 'border-red-200 bg-red-50'],
                        default    => ['dot' => 'bg-gray-300',  'chip' => 'border-gray-200 bg-gray-50'],
                    };
                    $title = ($labels[$key] ?? $key)
                        .' — zuletzt: '.($last ? $last->format('d.m.Y H:i') : 'nie')
                        .' · nächster Abruf: '.($state === 'overdue' ? 'jetzt fällig' : $due->format('d.m.Y H:i'));
                @endphp
                <span class="inline-flex items-center gap-1.5 pl-2 pr-2.5 py-1 rounded-full border text-[11px] {{ $c['chip'] }}" title="{{ $title }}">
                    <span class="w-1.5 h-1.5 rounded-full flex-shrink-0 {{ $c['dot'] }}"></span>
                    <span class="font-medium text-gray-700">{{ $labels[$key] ?? $key }}</span>
                    <span class="text-gray-400 tabular-nums">
                        {{ $last ? $last->diffForHumans(['short' => true]) : 'nie' }}
                    </span>
                </span>
            @endforeach
        </div>
    </div>
@endif
