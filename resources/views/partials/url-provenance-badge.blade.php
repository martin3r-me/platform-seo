{{-- Herkunfts-Badge: woher stammt die URL (Modul/Agentur/Wettbewerber). Key aus config('seo.provenance'). --}}
@props(['key'])
@php
    $p = config('seo.provenance.'.$key) ?? ['label' => ucfirst($key), 'classes' => 'bg-gray-50 text-gray-600 border-gray-200'];
@endphp
<span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium border {{ $p['classes'] }}">{{ $p['label'] }}</span>
