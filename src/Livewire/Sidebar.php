<?php

namespace Platform\Seo\Livewire;

use Livewire\Component;

class Sidebar extends Component
{
    public string $active = 'dashboard';

    public function render()
    {
        return view('seo::livewire.sidebar');
    }
}
