@props(['status' => 'active', 'httpStatus' => null])

@php
    $config = match($status) {
        'active' => ['bg' => 'bg-green-100', 'text' => 'text-green-700', 'icon' => 'heroicon-o-check-circle', 'label' => 'Aktiv'],
        'redirect' => ['bg' => 'bg-amber-100', 'text' => 'text-amber-700', 'icon' => 'heroicon-o-arrow-right-circle', 'label' => 'Redirect'],
        'error' => ['bg' => 'bg-red-100', 'text' => 'text-red-700', 'icon' => 'heroicon-o-exclamation-circle', 'label' => 'Fehler'],
        'pending' => ['bg' => 'bg-blue-100', 'text' => 'text-blue-700', 'icon' => 'heroicon-o-clock', 'label' => 'Ausstehend'],
        'deleted' => ['bg' => 'bg-gray-100', 'text' => 'text-gray-500', 'icon' => 'heroicon-o-trash', 'label' => 'Gelöscht'],
        default => ['bg' => 'bg-gray-100', 'text' => 'text-gray-500', 'icon' => 'heroicon-o-question-mark-circle', 'label' => ucfirst($status)],
    };
@endphp

<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium {{ $config['bg'] }} {{ $config['text'] }}">
    @svg($config['icon'], 'w-3.5 h-3.5')
    {{ $config['label'] }}
    @if($httpStatus)
        <span class="opacity-60">({{ $httpStatus }})</span>
    @endif
</span>
