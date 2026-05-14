<?php

namespace Platform\Seo\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;
use Platform\Seo\Models\SeoProject;

class Sidebar extends Component
{
    #[On('updateSidebar')]
    public function refresh(): void
    {
        // triggers re-render
    }

    public function render()
    {
        $user = Auth::user();
        $team = $user->currentTeam;

        $projects = SeoProject::where('team_id', $team->id)
            ->orderBy('name')
            ->get();

        return view('seo::livewire.sidebar', [
            'projects' => $projects,
        ]);
    }
}
