@props(['keyword', 'limit' => 5])
@php
    $competitors = $keyword->competitors->sortBy('position')->take($limit);
@endphp
@if($competitors->isNotEmpty())
    <div class="flex flex-wrap gap-1 mt-1">
        @foreach($competitors as $comp)
            <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 bg-gray-100 rounded text-[10px] text-gray-500" title="{{ $comp->url }}">
                <span class="font-medium text-gray-600">{{ $comp->position }}.</span>
                {{ Str::limit($comp->domain, 20) }}
            </span>
        @endforeach
    </div>
@endif
