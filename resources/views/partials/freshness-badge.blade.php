@props(['url', 'showNext' => false])

@php
    $status = $url->freshness_status;
    $lastFetched = $url->last_crawled_at;
    $nextDue = $url->next_refresh_due_at;

    $config = match($status) {
        'fresh'    => ['dot' => 'bg-green-500',  'text' => 'text-gray-500', 'label' => 'Aktuell'],
        'due_soon' => ['dot' => 'bg-amber-400',  'text' => 'text-amber-600', 'label' => 'Bald fällig'],
        'overdue'  => ['dot' => 'bg-red-500',    'text' => 'text-red-600',   'label' => 'Überfällig'],
        default    => ['dot' => 'bg-gray-300',   'text' => 'text-gray-400',  'label' => 'Nie geholt'],
    };

    // Per-Collector-Aufschlüsselung für den Tooltip
    $labels = [
        'keyword_metrics' => 'Keyword-Metriken',
        'gsc' => 'Search Console',
        'serp_ranking' => 'SERP-Rankings',
        'backlinks' => 'Backlinks',
        'on_page' => 'OnPage',
        'plausible' => 'Traffic',
        'llm_mentions' => 'LLM-Erwähnungen',
    ];
    $tooltipLines = [];
    foreach ($url->collectorFreshness() as $key => $info) {
        $name = $labels[$key] ?? $key;
        $when = $info['last'] ? $info['last']->diffForHumans() : 'nie';
        $tooltipLines[] = $name.': '.$when.($info['overdue'] ? ' (fällig)' : '');
    }
    $tooltip = ($lastFetched ? 'Zuletzt geholt: '.$lastFetched->format('d.m.Y H:i') : 'Noch nie geholt')
        .($nextDue ? "\nNächster Abruf: ".$nextDue->format('d.m.Y H:i') : '')
        ."\n\n".implode("\n", $tooltipLines);
@endphp

<span class="inline-flex items-center gap-1.5 text-[11px] tabular-nums {{ $config['text'] }}" title="{{ $tooltip }}">
    <span class="w-1.5 h-1.5 rounded-full flex-shrink-0 {{ $config['dot'] }}"></span>
    @if($lastFetched)
        <span>{{ $lastFetched->diffForHumans(['short' => true]) }}</span>
    @else
        <span>—</span>
    @endif
    @if($showNext)
        <span class="text-gray-300">·</span>
        @if($nextDue)
            <span class="{{ $status === 'overdue' ? 'text-red-600' : 'text-gray-400' }}">
                {{ $status === 'overdue' ? 'jetzt fällig' : $nextDue->diffForHumans() }}
            </span>
        @else
            <span class="text-gray-400">geplant nach 1. Abruf</span>
        @endif
    @endif
</span>
