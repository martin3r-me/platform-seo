{{-- Kunden-Tor · Sichtbarkeit / Inhalte: geplante und veröffentlichte Inhalte für die Website (nur Titel, Art, Stand). Daten: $d. --}}
@php
    $variant = ['published' => 'success', 'in_production' => 'info', 'queued' => 'warning', 'briefed' => 'neutral', 'draft' => 'neutral'];
@endphp
<div class="gp-wrap py-6">
    <div class="gp-card divide-y divide-[color:var(--nx-line)]">
        @foreach ($d['briefs'] as $b)
            <div class="flex items-center gap-3 px-4 py-3">
                @svg($b['status'] === 'published' ? 'heroicon-s-check-circle' : 'heroicon-o-document-text', 'w-5 h-5 shrink-0 ' . ($b['status'] === 'published' ? 'text-[color:var(--nx-success)]' : 'text-[color:var(--nx-faint)]'))
                <div class="min-w-0 flex-1">
                    <div class="truncate text-sm font-medium">{{ $b['name'] }}</div>
                    <div class="truncate text-xs text-[color:var(--nx-faint)]">
                        {{ collect([$b['type'] ? ucfirst(str_replace('_', ' ', $b['type'])) : null, $b['published_at'] ? 'veröffentlicht ' . $b['published_at']->copy()->locale('de')->isoFormat('D. MMM YYYY') : ($b['updated_at'] ? 'zuletzt bearbeitet ' . $b['updated_at']->copy()->locale('de')->diffForHumans() : null)])->filter()->implode(' · ') }}
                    </div>
                </div>
                <x-nx-badge :variant="$variant[$b['status']] ?? 'neutral'">{{ $b['status_label'] }}</x-nx-badge>
                @if ($b['url'])
                    <a href="{{ $b['url'] }}" target="_blank" rel="noopener" class="shrink-0 text-[color:var(--nx-faint)] hover:text-[color:var(--nx-text)]" title="Ansehen">@svg('heroicon-o-arrow-top-right-on-square', 'w-4 h-4')</a>
                @endif
            </div>
        @endforeach
    </div>
</div>
