<?php

namespace Nawasara\Zoom\Livewire\Recording\Section;

use Livewire\Component;
use Livewire\Attributes\Url;
use Nawasara\Ui\Livewire\Concerns\HasExport;
use Nawasara\Zoom\Models\ZoomRecording;
use Nawasara\Zoom\Repositories\ZoomRecordingRepository;
use Nawasara\Zoom\Services\ZoomClient;

class Table extends Component
{
    use HasExport;

    #[Url]
    public string $search = '';

    #[Url]
    public string $meetingId = '';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    #[Url]
    public int $page = 1;

    public array $selected = [];
    public bool $selectAll = false;

    public function render()
    {
        $repo = new ZoomRecordingRepository();

        $recordings = $repo->paginate(25, [
            'search' => $this->search,
            'meeting_id' => $this->meetingId,
            'from' => $this->from,
            'to' => $this->to,
        ]);

        // statistics() dipindahkan ke parent Index → hero stats. Sekarang
        // section/table cukup fokus ke listing — hindari double-query stats
        // yang sama di setiap re-render filter.
        return view('nawasara-zoom::livewire.pages.recording.section.table', [
            'recordings' => $recordings,
        ]);
    }

    public function resetFilters()
    {
        $this->reset('search', 'meetingId', 'from', 'to', 'page');
    }

    public function updatedSelectAll()
    {
        $repo = new ZoomRecordingRepository();
        $recordings = $repo->paginate(1000, [
            'search' => $this->search,
            'meeting_id' => $this->meetingId,
            'from' => $this->from,
            'to' => $this->to,
        ]);

        $this->selected = $this->selectAll
            ? $recordings->pluck('id')->map(fn ($id) => (string) $id)->toArray()
            : [];
    }

    public function resetSelection()
    {
        $this->selected = [];
        $this->selectAll = false;
    }

    public function deleteRecording(string $recordingId)
    {
        try {
            $client = app(ZoomClient::class);
            $repo = new ZoomRecordingRepository();
            $recording = $repo->find($recordingId);

            if ($recording) {
                $client->deleteRecording($recording->meeting_id, $recordingId);
                $repo->delete($recordingId);
                $this->dispatch('recording-deleted');
            }
        } catch (\Throwable $e) {
            $this->addError('delete', 'Failed to delete recording: '.$e->getMessage());
        }
    }

    /**
     * Export filename base — timestamp + extension appended by HasExport.
     */
    protected function exportFilename(): string
    {
        return 'zoom-recordings';
    }

    /**
     * Export FULL recordings dataset (ignoring active search/date filters)
     * per spec. Includes file URLs so admins can audit retention/storage
     * separately from the UI.
     */
    protected function exportData(): iterable
    {
        return ZoomRecording::query()
            ->with('owner')
            ->orderByDesc('start_time')
            ->get()
            ->map(fn (ZoomRecording $r) => [
                'Topic' => $r->topic,
                'Owner' => $r->owner?->full_name,
                'Meeting ID' => $r->meeting_id,
                'Recording ID' => $r->recording_id,
                'Start Time' => optional($r->start_time)->format('Y-m-d H:i'),
                'Duration (min)' => $r->duration_minutes,
                'File Size' => $r->file_size_mb,
                'File Type' => $r->file_type,
                'Recording Type' => $r->recording_type,
                'Status' => $r->status,
                'Download URL' => $r->download_url,
                'Play URL' => $r->play_url,
                'Retention Days' => $r->retention_days,
                'Last Synced' => optional($r->last_synced_at)->format('Y-m-d H:i'),
            ]);
    }
}
