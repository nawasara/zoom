<?php

namespace Nawasara\Zoom\Livewire\Meeting;

use Livewire\Component;
use Nawasara\Zoom\Models\ZoomMeeting;

class Edit extends Component
{
    public ZoomMeeting $meeting;

    public function render()
    {
        return view('nawasara-zoom::livewire.pages.meeting.edit', [
            'meeting' => $this->meeting,
        ])->layout('nawasara-ui::components.layouts.app');
    }
}
