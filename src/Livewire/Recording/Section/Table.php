<?php

namespace Nawasara\Zoom\Livewire\Recording\Section;

use Livewire\Component;
use Livewire\Attributes\Url;
use Nawasara\Zoom\Repositories\ZoomRecordingRepository;
use Nawasara\Zoom\Services\ZoomClient;

class Table extends Component
{
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
}
