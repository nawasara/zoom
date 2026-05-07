<div>
    {{-- Sync info bar --}}
    <div class="mb-3 flex items-center justify-between text-xs text-gray-500 dark:text-neutral-400">
        <div class="flex items-center gap-3">
            <span><strong class="text-gray-700 dark:text-neutral-300">{{ $statistics['total_users'] }}</strong> total users</span>
            <span class="text-gray-400">·</span>
            <span class="text-green-600 dark:text-green-400">{{ $statistics['active_users'] }} active</span>
        </div>
        <a href="{{ url('admin/sync/jobs') }}" wire:navigate class="text-emerald-700 dark:text-emerald-400 hover:underline font-medium">
            Lihat Sync Jobs →
        </a>
    </div>

    @php
        $licenseOptions = ['Basic' => 'Basic', 'Pro' => 'Pro', 'Business' => 'Business'];
        $statusOptions = ['active' => 'Active', 'inactive' => 'Inactive', 'pending' => 'Pending'];
    @endphp

    {{-- Toolbar — License + Status filters, search, reset, export. --}}
    <div class="space-y-2 mb-4">
        <div class="flex flex-col md:flex-row md:flex-nowrap md:items-center gap-2">
            <div class="flex flex-wrap items-center gap-2 shrink-0">
                <x-nawasara-ui::filter-panel
                    label="Filter"
                    :state="['licenseType' => $licenseType, 'status' => $status]"
                    :multiple="['licenseType', 'status']"
                    :labels="['licenseType' => $licenseOptions, 'status' => $statusOptions]"
                    :dimensions="['licenseType' => 'License', 'status' => 'Status']">
                    <x-nawasara-ui::filter-group label="License" model="licenseType" :items="$licenseOptions" icon="lucide-badge-check" />
                    <x-nawasara-ui::filter-group label="Status" model="status" :items="$statusOptions" icon="lucide-circle-check" />
                </x-nawasara-ui::filter-panel>
            </div>

            <x-nawasara-ui::search-input model="search" placeholder="Cari email atau nama..." />

            <div class="flex items-center gap-2 shrink-0">
                @if ($search || ! empty($licenseType) || ! empty($status))
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
                    tooltip="Ekspor data Zoom users" />
            </div>
        </div>

        <div wire:ignore data-filter-chips></div>

        @if ($search)
            <div class="flex flex-wrap items-center gap-2">
                <x-nawasara-ui::filter-chip label="Cari: {{ $search }}" model="search" />
            </div>
        @endif
    </div>

    {{-- No stickyLast: read-only listing, no per-row actions. --}}
    <x-nawasara-ui::table
        :headers="['Email', 'Name', 'License Type', 'Status', 'Last Login', '30D Meetings', 'Total Minutes']"
        :title="'Zoom Users ('.$users->total().' users)'">
        <x-slot:table>
            @forelse ($users as $user)
                <tr wire:key="user-{{ $user->id }}">
                    <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">
                        {{ $user->email }}
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-neutral-300">
                        {{ $user->full_name }}
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-neutral-300">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold
                            @switch($user->license_type)
                                @case('Basic') bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300 @break
                                @case('Pro') bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300 @break
                                @case('Business') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300 @break
                                @default bg-gray-100 text-gray-800 dark:bg-neutral-700 dark:text-neutral-300
                            @endswitch">
                            {{ $user->license_type ?? 'N/A' }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-neutral-300">
                        @php
                            $statusBadge = match($user->status) {
                                'active' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
                                'pending' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
                                default => 'bg-gray-100 text-gray-800 dark:bg-neutral-700 dark:text-neutral-300',
                            };
                        @endphp
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold {{ $statusBadge }}">
                            {{ ucfirst($user->status) }}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-neutral-300">
                        {{ $user->last_login_at?->diffForHumans() ?? 'Never' }}
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-neutral-300">
                        {{ $user->total_meetings_30d }}
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-neutral-300">
                        {{ $user->total_minutes_30d }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">
                        @if ($search || ! empty($licenseType) || ! empty($status))
                            <x-nawasara-ui::empty-state
                                icon="lucide-search-x"
                                title="Tidak ada user yang cocok"
                                description="Coba ubah filter atau hapus search keyword."
                                variant="filter"
                                inline />
                        @else
                            <x-nawasara-ui::empty-state
                                icon="lucide-users"
                                title="Belum ada Zoom user"
                                description="User yang ter-provisi di Zoom workspace akan auto-sync dan muncul di sini."
                                inline />
                        @endif
                    </td>
                </tr>
            @endforelse
        </x-slot:table>

        <x-slot:footer>
            {{ $users->links() }}
        </x-slot:footer>
    </x-nawasara-ui::table>
</div>
