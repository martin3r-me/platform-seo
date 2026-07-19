{{-- Konzept-Anker: „So funktioniert SEO" — Pipeline + Linsen-Idee. Alpine, via helpOpen gesteuert. --}}
@php $c = config('seo.help.concept'); @endphp
@if($c)
    <div x-show="helpOpen" style="display:none" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40" @click="helpOpen = false"></div>
        <div class="relative bg-white rounded-xl shadow-xl max-w-lg w-full max-h-[85vh] overflow-y-auto p-6"
             @click.stop @keydown.escape.window="helpOpen = false">
            <div class="flex items-start justify-between mb-3">
                <h2 class="text-[15px] font-semibold text-gray-900">{{ $c['title'] }}</h2>
                <button type="button" @click="helpOpen = false" class="text-gray-400 hover:text-gray-600">
                    @svg('heroicon-o-x-mark', 'w-5 h-5')
                </button>
            </div>

            <p class="text-[13px] text-gray-500 mb-4">{{ $c['intro'] }}</p>

            <ol class="space-y-2 mb-5">
                @foreach($c['pipeline'] as $i => $step)
                    <li class="flex gap-3">
                        <span class="flex-shrink-0 w-5 h-5 mt-0.5 rounded-full bg-indigo-50 text-indigo-600 text-[11px] font-semibold flex items-center justify-center tabular-nums">{{ $i + 1 }}</span>
                        <div>
                            <span class="text-[13px] font-medium text-gray-800">{{ $step['step'] }}</span>
                            <span class="text-[12px] text-gray-500"> — {{ $step['text'] }}</span>
                        </div>
                    </li>
                @endforeach
            </ol>

            <p class="text-[12px] font-medium text-gray-600 mb-2">{{ $c['lenses_intro'] }}</p>
            <ul class="space-y-1">
                @foreach($c['lenses'] as $lens)
                    <li class="text-[12px] text-gray-500 flex gap-2"><span class="text-indigo-400 flex-shrink-0">•</span><span>{{ $lens }}</span></li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
