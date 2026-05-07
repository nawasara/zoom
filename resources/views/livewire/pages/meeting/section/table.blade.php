<div>
    {{-- Sync info bar --}}
    <div class="mb-3 flex items-center justify-between text-xs text-gray-500 dark:text-neutral-400">
        <div class="flex items-center gap-3">
            <span><strong class="text-gray-700 dark:text-neutral-300">{{ $meetings->total() }}</strong> total meeting</span>
        </div>
        <a href="{{ url('admin/sync/jobs') }}" wire:navigate class="text-emerald-700 dark:text-emerald-400 hover:underline font-medium">
            Lihat Sync Jobs →
        </a>
    </div>

    @php
        // Single-select derived filter (upcoming/past are mutually exclusive
        // date scopes). filter-panel treats it as single because typeFilter
        // is NOT in the :multiple prop list.
        $typeOptions = ['upcoming' => 'Upcoming', 'past' => 'Past'];
    @endphp

    {{-- Time-window selector — segmented preset (Hari ini/7d/30d/Custom)
         scoped to the meeting `start_time` column via the repository's
         existing dateRange filter. Default 7 days. --}}
    <div class="mb-3">
        <x-nawasara-ui::time-window :window="$window" :from="$from" :to="$to" />
    </div>

    {{-- Toolbar — Type filter (single-select) + search + reset + export. --}}
    <div class="space-y-2 mb-4">
        <div class="flex flex-col md:flex-row md:flex-nowrap md:items-center gap-2">
            <div class="flex flex-wrap items-center gap-2 shrink-0">
                <x-nawasara-ui::filter-panel
                    label="Filter"
                    :state="['typeFilter' => $typeFilter]"
                    :labels="['typeFilter' => $typeOptions]"
                    :dimensions="['typeFilter' => 'Type']">
                    <x-nawasara-ui::filter-group label="Type" model="typeFilter" :items="$typeOptions" icon="lucide-calendar-clock" />
                </x-nawasara-ui::filter-panel>
            </div>

            <div class="relative w-full md:flex-1 md:min-w-0">
                <div class="absolute inset-y-0 start-0 flex items-center pointer-events-none ps-3.5">
                    <x-lucide-search class="shrink-0 size-4 text-gray-400 dark:text-neutral-500" />
                </div>
                <input type="text" wire:model.live.debounce.300ms="search"
                    placeholder="Cari topic atau agenda..."
                    class="h-10 ps-10 pe-4 block w-full border border-gray-200 rounded-lg text-sm focus:border-emerald-600 focus:ring-emerald-600 dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-200 dark:placeholder-neutral-500 dark:focus:ring-neutral-600" />
            </div>

            <div class="flex items-center gap-2 shrink-0">
                @if ($search || $typeFilter || $hostId || $window !== '7d' || $from || $to)
                    <x-nawasara-ui::tooltip text="Reset semua filter" placement="bottom">
                        <button type="button" wire:click="resetFilters"
                            aria-label="Reset filter"
                            class="inline-flex items-center justify-center size-10 rounded-lg border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-200 dark:hover:bg-neutral-700 shadow-sm transition-colors">
                            <x-lucide-x class="size-4" />
                        </button>
                    </x-nawasara-ui::tooltip>
                @endif

                <x-nawasara-ui::export-button
                    action="export"
                    tooltip="Ekspor data meeting (max 10rb baris)" />
            </div>
        </div>

        <div wire:ignore data-filter-chips></div>

        @if ($search)
            <div class="flex flex-wrap items-center gap-2">
                <x-nawasara-ui::filter-chip label="Cari: {{ $search }}" model="search" />
            </div>
        @endif
    </div>

    <x-nawasara-ui::table
        stickyLast
        :headers="['Topic', 'Host', 'Start Time', 'Duration', 'Status', 'Recording', '']"
        :title="'Meetings ('.$meetings->total().' meetings)'">
        <x-slot:table>
            @forelse ($meetings as $meeting)
                <tr wire:key="meeting-{{ $meeting->id }}">
                    <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-white max-w-md truncate">
                        {{ $meeting->topic }}
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-neutral-300">
                        @if ($meeting->host)
                            {{ $meeting->host->full_name }}
                        @else
                            <span class="text-gray-400">Unknown</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-neutral-300">
                        {{ $meeting->start_time?->format('d M Y H:i') ?? '-' }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-neutral-300">
                        {{ $meeting->duration }} min
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        @php
                            $statusBadge = match ($meeting->status) {
                                'started' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
                                'finished' => 'bg-gray-100 text-gray-800 dark:bg-neutral-700 dark:text-neutral-300',
                                default => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
                            };
                        @endphp
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold {{ $statusBadge }}">
                            {{ ucfirst(str_replace('_', ' ', $meeting->status)) }}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        @if ($meeting->can_record)
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300">
                                <x-lucide-circle-check class="size-3 mr-1" />
                                Recording
                            </span>
                        @else
                            <span class="text-gray-400">-</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                        <x-nawasara-ui::dropdown-menu-action :id="$meeting->id" :items="[
                            ['type' => 'href-navigate', 'label' => 'Edit', 'url' => route('nawasara-zoom.meeting.edit', $meeting), 'icon' => 'lucide-pencil', 'permission' => 'zoom.meeting.update'],
                        ]" />
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">
                        @if ($search || $typeFilter || $window !== '7d' || $from || $to)
                            <x-nawasara-ui::empty-state
                                icon="lucide-search-x"
                                title="Tidak ada meeting yang cocok"
                                description="Coba ubah periode/filter atau hapus search keyword."
                                variant="filter"
                                inline />
                        @else
                            <x-nawasara-ui::empty-state
                                icon="lucide-video"
                                title="Belum ada meeting 7 hari terakhir"
                                description="Pilih periode lebih panjang atau Custom untuk melihat data lebih lama."
                                inline />
                        @endif
                    </td>
                </tr>
            @endforelse
        </x-slot:table>

        <x-slot:footer>
            {{ $meetings->links() }}
        </x-slot:footer>
    </x-nawasara-ui::table>
</div>
