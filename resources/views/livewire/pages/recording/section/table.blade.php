<div>
    <x-nawasara-ui::sync-info-bar
        :lastSyncedAt="$this->lastSyncedAt"
        neverSyncedMessage='Belum pernah di-sync. Klik tombol sync untuk menarik recording + riwayat dari Zoom.' />

    {{-- Toolbar — time-window inline left, search center, export right.
         No filter dimensions in visible UI (meetingId is URL-only programmatic
         param). Time window scoped to start_time via repo dateRange. --}}
    <div class="space-y-2 mb-4">
        <div class="flex flex-col md:flex-row md:flex-nowrap md:items-center gap-2">
            {{-- Time window pinned left (no other filter to keep it
                 grouped with). --}}
            <x-nawasara-ui::time-window :window="$window" :from="$from" :to="$to"
                :presets="['all' => 'Semua', 'today' => 'Hari ini', '7d' => '7 hari', '30d' => '30 hari']" />

            {{-- Search zone — fills available space. --}}
            <x-nawasara-ui::search-input model="search" placeholder="Cari topic recording..." />

            {{-- Action zone. Export + reset. Export gated on
                 zoom.recording.delete (only admins who can manage recordings
                 should pull URLs/sizes). --}}
            <div class="flex items-center gap-2 shrink-0">
                @if ($search || $meetingId || $window !== '7d' || $from || $to)
                    <x-nawasara-ui::tooltip text="Reset semua filter" placement="bottom">
                        <button type="button" wire:click="resetFilters"
                            aria-label="Reset filter"
                            class="inline-flex items-center justify-center size-10 rounded-lg border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-200 dark:hover:bg-neutral-700 shadow-sm transition-colors">
                            <x-lucide-x class="size-4" />
                        </button>
                    </x-nawasara-ui::tooltip>
                @endif

                <x-nawasara-ui::icon-button icon="refresh-cw" tooltip="Sync recording + riwayat dari Zoom"
                    wire:click="syncNow" loadingTarget="syncNow" />

                <x-nawasara-ui::export-button
                    action="export"
                    tooltip="Ekspor recording metadata"
                    permission="zoom.recording.delete" />
            </div>
        </div>

        @if ($search)
            <div class="flex flex-wrap items-center gap-2">
                <x-nawasara-ui::filter-chip label="Cari: {{ $search }}" model="search" />
            </div>
        @endif
    </div>

    <x-nawasara-ui::table
        stickyLast
        :headers="['Topic', 'Owner', 'Start Time', 'Duration', 'File Size', 'Status', '']"
        :title="'Recordings ('.$recordings->total().' recordings)'">
        <x-slot:table>
            @forelse ($recordings as $recording)
                <tr wire:key="recording-{{ $recording->id }}">
                    <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-white max-w-md truncate">
                        {{ $recording->topic }}
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-neutral-300">
                        @if ($recording->owner)
                            {{ $recording->owner->full_name }}
                        @else
                            <span class="text-gray-400">Unknown</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-neutral-300">
                        {{ $recording->start_time?->format('d M Y H:i') ?? '-' }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-neutral-300">
                        {{ $recording->duration_minutes }} min
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-neutral-300">
                        {{ $recording->file_size_mb }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        @php
                            $statusBadge =
                                $recording->status === 'completed'
                                    ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300'
                                    : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300';
                        @endphp
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold {{ $statusBadge }}">
                            {{ ucfirst($recording->status) }}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                        @php
                            $actions = [];
                            if ($recording->download_url) {
                                $actions[] = [
                                    'type' => 'href',
                                    'label' => 'Download',
                                    'url' => $recording->download_url,
                                    'target' => '_blank',
                                    'icon' => 'lucide-download',
                                ];
                            }
                            $actions[] = [
                                'type' => 'click',
                                'label' => 'Hapus',
                                'wire:click' => "deleteRecording('{$recording->recording_id}')",
                                'icon' => 'lucide-trash-2',
                                'confirm' => 'Hapus recording ini? Aksi ini juga akan menghapus file di Zoom Cloud.',
                                'permission' => 'zoom.recording.delete',
                            ];
                        @endphp
                        <x-nawasara-ui::dropdown-menu-action :id="$recording->id" :items="$actions" />
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">
                        @if ($search || $window !== '7d' || $from || $to)
                            <x-nawasara-ui::empty-state
                                icon="lucide-search-x"
                                title="Tidak ada recording yang cocok"
                                description="Coba ubah periode/filter atau hapus search keyword."
                                variant="filter"
                                inline />
                        @else
                            <x-nawasara-ui::empty-state
                                icon="lucide-video-off"
                                title="Belum ada recording 7 hari terakhir"
                                description="Pilih periode lebih panjang atau Custom untuk melihat data lebih lama."
                                inline />
                        @endif
                    </td>
                </tr>
            @endforelse
        </x-slot:table>

        <x-slot:footer>
            {{ $recordings->links() }}
        </x-slot:footer>
    </x-nawasara-ui::table>
</div>
