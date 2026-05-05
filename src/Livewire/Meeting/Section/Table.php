<?php

namespace Nawasara\Zoom\Livewire\Meeting\Section;

use Livewire\Component;
use Livewire\Attributes\Url;
use Nawasara\Zoom\Repositories\ZoomMeetingRepository;

class Table extends Component
{
    #[Url]
    public string $search = '';

    #[Url]
    public string $hostId = '';

    #[Url]
    public string $typeFilter = '';

    #[Url]
    public int $page = 1;

    public array $selected = [];
    public bool $selectAll = false;

    public function render()
    {
        $repo = new ZoomMeetingRepository();

        $meetings = $repo->paginate(25, [
            'search' => $this->search,
            'host_id' => $this->hostId,
            'type' => $this->typeFilter,
        ]);

        return view('nawasara-zoom::livewire.pages.meeting.section.table', [
            'meetings' => $meetings,
        ]);
    }

    public function resetFilters()
    {
        $this->reset('search', 'hostId', 'typeFilter', 'page');
    }

    public function updatedSelectAll()
    {
        $repo = new ZoomMeetingRepository();
        $meetings = $repo->paginate(1000, [
            'search' => $this->search,
            'host_id' => $this->hostId,
            'type' => $this->typeFilter,
        ]);

        $this->selected = $this->selectAll
            ? $meetings->pluck('id')->map(fn ($id) => (string) $id)->toArray()
            : [];
    }

    public function resetSelection()
    {
        $this->selected = [];
        $this->selectAll = false;
    }
}
