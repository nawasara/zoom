<div>
    {{-- Sync info bar — totals sudah di-render di hero stats Index, di sini
         cukup link ke sync jobs untuk troubleshooting. --}}
    <div class="mb-3 flex items-center justify-end text-xs text-gray-500 dark:text-neutral-400">
        <a href="{{ url('admin/sync/jobs') }}" wire:navigate class="text-emerald-700 dark:text-emerald-400 hover:underline font-medium">
            Lihat Sync Jobs →
        </a>
    </div>

    <x-nawasara-ui::filter-bar searchPlaceholder="Cari topic..." searchModel="search">
        <x-slot:actions>
            <x-nawasara-ui::button color="neutral" variant="outline" size="sm" wire:click="resetFilters">
                <x-slot:icon>
                    <x-lucide-x class="size-4" />
                </x-slot:icon>
                Reset
            </x-nawasara-ui::button>
        </x-slot:actions>

        <x-slot:chips>
            @if ($search)
                <x-nawasara-ui::filter-chip label="Cari: {{ $search }}" model="search" />
            @endif
        </x-slot:chips>
    </x-nawasara-ui::filter-bar>

    <x-nawasara-ui::table :headers="['Topic', 'Owner', 'Start Time', 'Duration', 'File Size', 'Status', 'Actions']" :title="'Recordings (' . $recordings->total() . ' recordings)'">
        <x-slot:table>
            @forelse ($recordings as $recording)
                <tr wire:key="recording-{{ $recording->id }}">
                    <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">
                        {{ $recording->topic }}
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-neutral-300">
                        @if ($recording->owner)
                            {{ $recording->owner->full_name }}
                        @else
                            <span class="text-gray-400">Unknown</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-neutral-300">
                        {{ $recording->start_time?->format('d M Y H:i') ?? '-' }}
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-neutral-300">
                        {{ $recording->duration_minutes }} min
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-neutral-300">
                        {{ $recording->file_size_mb }}
                    </td>
                    <td class="px-6 py-4 text-sm">
                        @php
                            $statusBadge =
                                $recording->status === 'completed'
                                    ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300'
                                    : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300';
                        @endphp
                        <span
                            class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold {{ $statusBadge }}">
                            {{ ucfirst($recording->status) }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm space-x-2 text-center">
                        @if ($recording->download_url)
                            <a href="{{ $recording->download_url }}" target="_blank"
                                class="text-emerald-700 dark:text-emerald-400 hover:underline text-xs font-medium">
                                <x-lucide-download class="size-4 inline" />
                                Download
                            </a>
                        @endif
                        @can('zoom.recording.delete')
                            <x-nawasara-ui::button variant="link" color="danger" size="sm"
                                wire:click="deleteRecording('{{ $recording->recording_id }}')"
                                wire:confirm="Delete this recording?"
                                class="text-xs">
                                <x-slot:icon><x-lucide-trash-2 /></x-slot:icon>
                                Delete
                            </x-nawasara-ui::button>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">
                        <x-nawasara-ui::empty-state
                            icon="lucide-video-off"
                            title="Belum ada recording"
                            description="Cloud recording dari meeting akan tersedia di sini setelah meeting selesai (delay ~5-15 menit)."
                            inline />
                    </td>
                </tr>
            @endforelse
        </x-slot:table>
    </x-nawasara-ui::table>

    <div class="mt-6">
        {{ $recordings->links() }}
    </div>
</div>
