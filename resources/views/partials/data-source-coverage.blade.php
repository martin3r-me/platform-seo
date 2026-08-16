@props(['urls' => null])

{{--
    Datenquellen-Abdeckung: wie viele der Sites haben je Quelle überhaupt Daten
    (Plausible/GSC/Rankings/…). Macht die Datenbasis offensichtlich — auf einen
    Blick, ohne in jede URL zu klicken. Quelle: SeoUrl::collectorFreshness().
--}}
@php
    $covUrls = $urls ?? collect();
    $covTotal = $covUrls->count();
    $covSources = [
        'plausible' => 'Plausible',
        'gsc' => 'GSC',
        'serp_ranking' => 'Rankings',
        'on_page' => 'On-Page',
        'backlinks' => 'Backlinks',
    ];
    $covCounts = array_fill_keys(array_keys($covSources), 0);
    foreach ($covUrls as $covU) {
        $covFresh = method_exists($covU, 'collectorFreshness') ? $covU->collectorFreshness() : [];
        foreach ($covSources as $covKey => $covLabel) {
            if (! empty($covFresh[$covKey]['last'])) {
                $covCounts[$covKey]++;
            }
        }
    }
@endphp

@if($covTotal > 0)
    <div class="flex items-center gap-1.5 flex-wrap">
        <span class="text-[10px] uppercase tracking-wide text-gray-400 mr-0.5">Datenquellen</span>
        @foreach($covSources as $covKey => $covLabel)
            @php($covN = $covCounts[$covKey])
            <span class="inline-flex items-center gap-1 text-[11px] px-2 py-0.5 rounded-full border {{ $covN > 0 ? 'border-gray-200 bg-white' : 'border-gray-100 bg-gray-50' }}"
                  title="{{ $covLabel }}: {{ $covN }} von {{ $covTotal }} Sites haben Daten">
                <span class="w-1.5 h-1.5 rounded-full {{ $covN === 0 ? 'bg-gray-300' : ($covN >= $covTotal ? 'bg-green-500' : 'bg-amber-400') }}"></span>
                <span class="{{ $covN > 0 ? 'text-gray-700' : 'text-gray-400' }}">{{ $covLabel }}</span>
                <span class="tabular-nums {{ $covN > 0 ? 'text-gray-500' : 'text-gray-300' }}">{{ $covN }}/{{ $covTotal }}</span>
            </span>
        @endforeach
    </div>
@endif
