<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Wirkungsräume" icon="heroicon-o-rocket-launch" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'SEO', 'icon' => 'magnifying-glass-circle', 'route' => 'seo.dashboard'],
            ['label' => 'Wirkungsräume'],
        ]" />
    </x-slot>

    <x-slot name="sidebar">
        <livewire:seo.sidebar />
    </x-slot>

    <x-ui-page-container>
        <div class="max-w-5xl">
            <div class="flex items-start justify-between gap-4 mb-1">
                <div>
                    <h1 class="text-lg font-semibold text-gray-900">Wirkungsräume</h1>
                    <p class="text-[13px] text-gray-500">Steuer-Scopes: kontrollierte URLs + Ziel. Hier wird gehandelt — im Gegensatz zu Listen (Beobachten).</p>
                </div>
                <button wire:click="$toggle('showCreate')" class="shrink-0 text-[13px] font-medium px-3 py-1.5 rounded-md bg-gray-900 text-white hover:bg-gray-700">
                    + Neuer Wirkungsraum
                </button>
            </div>

            @if($showCreate)
                <div class="mt-4 bg-white rounded-lg border border-gray-200 p-4 space-y-3">
                    <input type="text" wire:model="newName" placeholder="Name (z. B. Catering NRW)"
                           class="w-full text-[13px] border border-gray-300 rounded-md px-3 py-2" />
                    <textarea wire:model="newGoal" rows="2" placeholder="Ziel — welche Themen soll der Verbund dominieren?"
                              class="w-full text-[13px] border border-gray-300 rounded-md px-3 py-2"></textarea>
                    <div class="flex items-center gap-2">
                        <button wire:click="create" class="text-[13px] font-medium px-3 py-1.5 rounded-md bg-gray-900 text-white hover:bg-gray-700">Anlegen</button>
                        <button wire:click="$set('showCreate', false)" class="text-[13px] text-gray-500 hover:text-gray-800">Abbrechen</button>
                    </div>
                </div>
            @endif

            <div class="mt-5 space-y-2">
                @forelse($items as $wr)
                    <div class="bg-white rounded-lg border border-gray-200 hover:border-gray-300 transition-colors">
                        <div class="flex items-start justify-between gap-4 p-4">
                            <a href="{{ route('seo.portfolios.show', $wr->id) }}" wire:navigate class="block min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-[14px] text-gray-900">{{ $wr->name }}</span>
                                    @if($wr->children_count > 0)
                                        <span class="text-[10px] uppercase tracking-wide text-gray-400">{{ $wr->children_count }} Unter-Räume</span>
                                    @endif
                                </div>
                                @if($wr->goal)
                                    <p class="text-[12px] text-gray-500 line-clamp-1 mt-0.5">{{ $wr->goal }}</p>
                                @endif
                            </a>
                            <div class="shrink-0 flex items-center gap-6 text-right">
                                <div>
                                    <div class="text-[15px] font-semibold text-gray-900 tabular-nums">{{ number_format($wr->agg_visibility, 0) }}</div>
                                    <div class="text-[10px] uppercase tracking-wide text-gray-400">Sichtbarkeit</div>
                                </div>
                                <div>
                                    <div class="text-[15px] font-semibold text-gray-700 tabular-nums">{{ $wr->urls_count }}</div>
                                    <div class="text-[10px] uppercase tracking-wide text-gray-400">URLs</div>
                                </div>
                                <button wire:click="deletePortfolio({{ $wr->id }})"
                                        wire:confirm="Wirkungsraum „{{ $wr->name }}" löschen? Die URLs bleiben erhalten — nur der Steuer-Scope wird entfernt."
                                        class="text-gray-300 hover:text-rose-600 transition-colors" title="Wirkungsraum löschen">
                                    @svg('heroicon-o-trash', 'w-4 h-4')
                                </button>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="bg-white rounded-lg border border-dashed border-gray-200 p-8 text-center">
                        <p class="text-[13px] text-gray-500">Noch kein Wirkungsraum. Ein Verbund kontrollierter URLs mit einem Ziel — hier wird ausgesteuert.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </x-ui-page-container>
</x-ui-page>
