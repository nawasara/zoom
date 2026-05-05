<?php

namespace Nawasara\Zoom\Livewire\User\Section;

use Livewire\Component;
use Livewire\Attributes\Url;
use Nawasara\Zoom\Repositories\ZoomUserRepository;

class Table extends Component
{
    #[Url]
    public string $search = '';

    #[Url]
    public string $licenseType = '';

    #[Url]
    public string $status = '';

    #[Url]
    public int $page = 1;

    public array $selected = [];
    public bool $selectAll = false;

    public function render()
    {
        $repo = new ZoomUserRepository();

        $users = $repo->paginate(25, [
            'search' => $this->search,
            'license_type' => $this->licenseType,
            'status' => $this->status,
        ]);

        return view('nawasara-zoom::livewire.pages.user.section.table', [
            'users' => $users,
            'statistics' => $repo->statistics(),
        ]);
    }

    public function resetFilters()
    {
        $this->reset('search', 'licenseType', 'status', 'page');
    }

    public function updatedSelectAll()
    {
        $repo = new ZoomUserRepository();
        $users = $repo->paginate(1000, [
            'search' => $this->search,
            'license_type' => $this->licenseType,
            'status' => $this->status,
        ]);

        $this->selected = $this->selectAll
            ? $users->pluck('id')->map(fn ($id) => (string) $id)->toArray()
            : [];
    }

    public function resetSelection()
    {
        $this->selected = [];
        $this->selectAll = false;
    }
}
