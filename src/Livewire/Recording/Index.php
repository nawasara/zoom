<?php

namespace Nawasara\Zoom\Livewire\Recording;

use Livewire\Component;

class Index extends Component
{
    public function render()
    {
        return view('nawasara-zoom::livewire.pages.recording.index')
            ->layout('nawasara-ui::components.layouts.app');
    }
}
