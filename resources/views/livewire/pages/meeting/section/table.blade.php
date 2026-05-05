<div>
    {{-- Sync info bar --}}
    <div class="mb-3 flex items-center justify-between text-xs text-gray-500 dark:text-neutral-400">
        <div class="flex items-center gap-3">
            <span class="text-blue-600">Total: {{ $meetings->total() }}</span>
        </div>
        <a href="{{ url('admin/sync/jobs') }}" wire:navigate class="text-blue-600 hover:underline">
            Lihat Sync Jobs →
        </a>
    </div>

    <x-nawasara-ui::filter-bar searchPlaceholder="Cari topic atau agenda..." searchModel="search">
        <x-nawasara-ui::filter-dropdown label="Type" model="typeFilter" :items="['all' => 'Semua Tipe', 'upcoming' => 'Upcoming', 'past' => 'Past']" />

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
            @if ($typeFilter && $typeFilter !== 'all')
                <x-nawasara-ui::filter-chip label="Type: {{ ucfirst($typeFilter) }}" model="typeFilter" />
            @endif
        </x-slot:chips>
    </x-nawasara-ui::filter-bar>

    <x-nawasara-ui::table :headers="['Topic', 'Host', 'Start Time', 'Duration', 'Status', 'Recording', 'Actions']" :title="'Meetings (' . $meetings->total() . ' meetings)'">
        <x-slot:table>
            @forelse ($meetings as $meeting)
                <tr wire:key="meeting-{{ $meeting->id }}">
                    <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">
                        {{ $meeting->topic }}
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-neutral-300">
                        @if ($meeting->host)
                            {{ $meeting->host->full_name }}
                        @else
                            <span class="text-gray-400">Unknown</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-neutral-300">
                        {{ $meeting->start_time?->format('d M Y H:i') ?? '-' }}
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-neutral-300">
                        {{ $meeting->duration }} min
                    </td>
                    <td class="px-6 py-4 text-sm">
                        @php
                            $statusBadge = match ($meeting->status) {
                                'started' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
                                'finished' => 'bg-gray-100 text-gray-800 dark:bg-neutral-700 dark:text-neutral-300',
                                default => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
                            };
                        @endphp
                        <span
                            class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold {{ $statusBadge }}">
                            {{ ucfirst(str_replace('_', ' ', $meeting->status)) }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm">
                        @if ($meeting->can_record)
                            <span
                                class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300">
                                <x-lucide-circle-check class="size-3 mr-1" />
                                Recording
                            </span>
                        @else
                            <span class="text-gray-400">-</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-sm text-center">
                        <x-nawasara-ui::button :href="route('nawasara-zoom.meeting.edit', $meeting)" size="sm" variant="ghost" color="primary"
                            permission="zoom.meeting.update">
                            <x-lucide-edit class="size-4" />
                        </x-nawasara-ui::button>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="px-6 py-8 text-center text-gray-500">
                        <x-lucide-inbox class="size-8 mx-auto mb-2 text-gray-400" />
                        <p>No meetings found</p>
                    </td>
                </tr>
            @endforelse
        </x-slot:table>
    </x-nawasara-ui::table>

    <div class="mt-6">
        {{ $meetings->links() }}
    </div>
</div>
