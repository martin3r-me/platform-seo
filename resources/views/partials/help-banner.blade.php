{{-- Pro-Linse-Hilfe: „Was ist das / was tue ich hier" — wegklickbar, gemerkt (localStorage). --}}
@props(['lens'])
@php $h = config('seo.help.lenses.'.$lens); @endphp
@if($h)
    <div x-data="{ show: localStorage.getItem('seo.help.{{ $lens }}') !== 'dismissed' }"
         x-show="show" style="display:none" class="mb-5">
        <x-ui-info-banner icon="heroicon-o-information-circle" :title="$h['title']" variant="info">
            {{ $h['what'] }}
            <x-slot name="actions">
                <div class="flex items-center gap-4 text-[12px]">
                    @if(!empty($h['next']) && \Illuminate\Support\Facades\Route::has($h['next']['route']))
                        <a href="{{ route($h['next']['route']) }}" wire:navigate class="font-medium underline">{{ $h['next']['label'] }} →</a>
                    @endif
                    <button type="button"
                            @click="localStorage.setItem('seo.help.{{ $lens }}', 'dismissed'); show = false"
                            class="opacity-70 hover:opacity-100">Verstanden, ausblenden</button>
                </div>
            </x-slot>
        </x-ui-info-banner>
    </div>
@endif
