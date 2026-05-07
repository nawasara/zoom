<?php

namespace Nawasara\Zoom\Livewire\Meeting\Section;

use Livewire\Component;
use Livewire\Attributes\Url;
use Nawasara\Ui\Livewire\Concerns\HasExport;
use Nawasara\Ui\Livewire\Concerns\HasTimeWindow;
use Nawasara\Zoom\Models\ZoomMeeting;
use Nawasara\Zoom\Repositories\ZoomMeetingRepository;

class Table extends Component
{
    use HasExport;
    // HasTimeWindow declares $window / $from / $to, plus resolveTimeWindow()
    // that we use below to translate the active preset into Y-m-d bounds for
    // the repository's existing dateRange filter.
    use HasTimeWindow;

    #[Url]
    public string $search = '';

    #[Url]
    public string $hostId = '';

    /**
     * Single-select derived filter: 'upcoming' | 'past' | '' (all).
     * Stays scalar (not array) because the underlying scopes are mutually
     * exclusive date predicates - multi-select wouldn't make semantic sense.
     */
    #[Url]
    public string $typeFilter = '';

    public array $selected = [];
    public bool $selectAll = false;

    public function render()
    {
        $repo = new ZoomMeetingRepository();

        $meetings = $repo->paginate(25, $this->buildFilters());

        return view('nawasara-zoom::livewire.pages.meeting.section.table', [
            'meetings' => $meetings,
        ]);
    }

    /**
     * Translate component state into the shape ZoomMeetingRepository expects.
     * The repo already supports `from`/`to` Y-m-d strings via dateRange, so
     * we resolve our preset window into bounds here instead of mutating
     * $this->from / $this->to (those stay reserved for Custom mode).
     *
     * @return array<string, mixed>
     */
    protected function buildFilters(): array
    {
        [$from, $to] = $this->resolveTimeWindow();

        return [
            'search' => $this->search,
            'host_id' => $this->hostId,
            'type' => $this->typeFilter,
            'from' => $from?->toDateString(),
            'to' => $to?->toDateString(),
        ];
    }

    public function resetFilters()
    {
        $this->reset('search', 'hostId', 'typeFilter', 'window', 'from', 'to');
        $this->resetPage();
    }

    public function updatedSelectAll()
    {
        $repo = new ZoomMeetingRepository();
        $meetings = $repo->paginate(1000, $this->buildFilters());

        $this->selected = $this->selectAll
            ? $meetings->pluck('id')->map(fn ($id) => (string) $id)->toArray()
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
        return 'zoom-meetings';
    }

    /**
     * Export FULL meetings dataset (no filter) per spec. Capped to keep xlsx
     * generation bounded; meetings table can grow large in active orgs.
     */
    protected function exportData(): iterable
    {
        return ZoomMeeting::query()
            ->with('host')
            ->orderByDesc('start_time')
            ->limit(10000)
            ->get()
            ->map(fn (ZoomMeeting $m) => [
                'Meeting ID' => $m->meeting_id,
                'Topic' => $m->topic,
                'Host' => $m->host?->full_name,
                'Host Email' => $m->host?->email,
                'Start Time' => optional($m->start_time)->format('Y-m-d H:i'),
                'Duration (min)' => $m->duration,
                'Status' => $m->status,
                'Can Record' => $m->can_record ? 'Yes' : 'No',
                'Type' => $m->type,
                'Timezone' => $m->timezone,
                'Agenda' => $m->agenda,
                'Last Synced' => optional($m->last_synced_at)->format('Y-m-d H:i'),
            ]);
    }
}
