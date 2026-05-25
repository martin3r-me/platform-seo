@props(['keyword'])
@if($keyword->monthly_volumes && count($keyword->monthly_volumes) >= 6)
    @php
        $mv = $keyword->monthly_volumes;
        $maxVol = max($mv);
        $monthNames = ['', 'J','F','M','A','M','J','J','A','S','O','N','D'];
        $currentMonth = (int) date('n');
        $tooltipParts = [];
        foreach (range(1, 12) as $m) {
            $tooltipParts[] = $monthNames[$m] . ': ' . number_format($mv[$m] ?? 0);
        }
        $tooltip = implode(' | ', $tooltipParts);
    @endphp
    <div class="inline-flex flex-col items-center">
        <div class="flex items-center gap-1">
            @include('seo::partials.sv-badge', ['volume' => $keyword->median_volume])
            <span class="text-[9px] text-gray-400">{{ number_format($keyword->min_volume) }}–{{ number_format($keyword->max_volume) }}</span>
        </div>
        <div class="flex items-end gap-px h-4 justify-center mt-0.5" title="{{ $tooltip }}">
            @foreach(range(1, 12) as $m)
                @php
                    $v = $mv[$m] ?? 0;
                    $h = $maxVol > 0 ? max(1, round(($v / $maxVol) * 16)) : 1;
                    $isCurrent = $m === $currentMonth;
                    $isPeak = $m === ($keyword->peak_month ?? 0);
                @endphp
                <div class="w-1 rounded-t {{ $isPeak ? 'bg-blue-500' : ($isCurrent ? 'bg-blue-300' : 'bg-gray-200') }}"
                     style="height: {{ $h }}px"></div>
            @endforeach
        </div>
        @if($keyword->peak_month)
            <div class="text-[9px] text-gray-400 text-center mt-0.5">
                Peak {{ $monthNames[$keyword->peak_month] ?? '' }}
                @if($keyword->seasonality_index && $keyword->seasonality_index >= 1.5)
                    <span class="text-orange-500">{{ number_format($keyword->seasonality_index, 1) }}x</span>
                @endif
            </div>
        @endif
    </div>
@else
    @include('seo::partials.sv-badge', ['volume' => $keyword->search_volume])
@endif
