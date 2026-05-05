<?php

namespace Nawasara\Zoom\Livewire\Meeting;

use Livewire\Component;

class Index extends Component
{
    public function render()
    {
        return view('nawasara-zoom::livewire.pages.meeting.index')
            ->layout('nawasara-ui::components.layouts.app');
    }
}
