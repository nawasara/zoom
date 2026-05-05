<?php

namespace Nawasara\Zoom\Livewire\Meeting;

use Livewire\Component;

class Create extends Component
{
    public function render()
    {
        return view('nawasara-zoom::livewire.pages.meeting.create')
            ->layout('nawasara-ui::components.layouts.app');
    }
}
