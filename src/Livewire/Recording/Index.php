<?php

namespace Nawasara\Zoom\Livewire\Recording;

use Livewire\Attributes\Computed;
use Livewire\Component;
use Nawasara\Zoom\Repositories\ZoomRecordingRepository;

class Index extends Component
{
    /**
     * Hero stats untuk Recordings page.
     *
     * KPI yang dipilih:
     * - Total: jumlah recording yang ter-track
     * - Completed: siap stream/download
     * - Storage: total size dalam GB (capacity awareness)
     * - Expiring 7 hari: recording yang akan auto-delete (perlu action ekspor)
     */
    #[Computed]
    public function stats(): array
    {
        $s = (new ZoomRecordingRepository())->stats();

        $sizeGb = round($s['total_size_bytes'] / (1024 ** 3), 2);

        return [
            ['label' => 'Total Recordings', 'value' => number_format($s['total']), 'icon' => 'lucide-video', 'color' => 'primary'],
            ['label' => 'Completed', 'value' => number_format($s['completed']), 'icon' => 'lucide-circle-check', 'color' => 'success', 'description' => 'siap stream / download'],
            ['label' => 'Storage', 'value' => number_format($sizeGb, 2).' GB', 'icon' => 'lucide-hard-drive', 'color' => 'neutral', 'description' => 'total file size'],
            ['label' => 'Expiring 7 Hari', 'value' => number_format($s['expiring_soon']), 'icon' => 'lucide-clock-alert', 'color' => $s['expiring_soon'] > 0 ? 'warning' : 'neutral', 'description' => $s['expiring_soon'] > 0 ? 'perlu di-archive' : 'aman'],
        ];
    }

    public function render()
    {
        return view('nawasara-zoom::livewire.pages.recording.index')
            ->layout('nawasara-ui::components.layouts.app');
    }
}
