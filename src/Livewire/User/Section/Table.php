<?php

namespace Nawasara\Zoom\Livewire\User\Section;

use Livewire\Component;
use Livewire\Attributes\Url;
use Nawasara\Ui\Livewire\Concerns\HasArrayFilters;
use Nawasara\Ui\Livewire\Concerns\HasExport;
use Nawasara\Zoom\Models\ZoomUser;
use Nawasara\Zoom\Repositories\ZoomUserRepository;

class Table extends Component
{
    use HasArrayFilters;
    use HasExport;

    #[Url]
    public string $search = '';

    /**
     * License-type filter as multi-select array (e.g. ['Pro', 'Business']).
     * Empty array == no filter. Type hint omitted so legacy bookmarks
     * (`?licenseType=Pro`) survive hydration; HasArrayFilters coerces
     * the scalar at boot time.
     *
     * @var array<int, string>
     */
    #[Url]
    public $licenseType = [];

    /**
     * Status filter as multi-select array (['active', 'inactive']).
     * Empty array == no filter.
     *
     * @var array<int, string>
     */
    #[Url]
    public $status = [];

    #[Url]
    public int $page = 1;

    public array $selected = [];
    public bool $selectAll = false;

    /**
     * Filters that may receive scalar values from legacy bookmarks.
     * HasArrayFilters wraps any scalar into [scalar] at boot.
     */
    protected function arrayFilters(): array
    {
        return ['licenseType', 'status'];
    }

    #[\Livewire\Attributes\Computed]
    public function lastSyncedAt(): ?string
    {
        return (new ZoomUserRepository())->lastSyncedAt()?->diffForHumans();
    }

    public function syncNow(): void
    {
        \Illuminate\Support\Facades\Gate::authorize('zoom.user.view');

        (new ZoomUserRepository())->syncNow();
        $this->dispatch('toast', type: 'info', message: 'Sinkronisasi user Zoom berjalan di latar belakang.');
    }

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

    /**
     * Export filename base — timestamp + extension appended by HasExport.
     */
    protected function exportFilename(): string
    {
        return 'zoom-users';
    }

    /**
     * Export FULL Zoom user dataset (no filter applied) per spec. Includes
     * usage stats so org admins can offline-audit license utilization.
     */
    protected function exportData(): iterable
    {
        return ZoomUser::query()
            ->orderBy('email')
            ->get()
            ->map(fn (ZoomUser $u) => [
                'Email' => $u->email,
                'First Name' => $u->first_name,
                'Last Name' => $u->last_name,
                'Full Name' => $u->full_name,
                'User Type' => $u->user_type,
                'License Type' => $u->license_type,
                'Status' => $u->status,
                'Last Login' => optional($u->last_login_at)->format('Y-m-d H:i'),
                '30D Meetings' => $u->total_meetings_30d,
                '30D Minutes' => $u->total_minutes_30d,
                'Zoom Created' => optional($u->zoom_created_at)->format('Y-m-d H:i'),
                'Last Synced' => optional($u->last_synced_at)->format('Y-m-d H:i'),
            ]);
    }
}
