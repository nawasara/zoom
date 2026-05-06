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

    <x-nawasara-ui::filter-bar searchPlaceholder="Cari email atau nama..." searchModel="search">
        <x-nawasara-ui::filter-dropdown label="License Type" model="licenseType" :items="['all' => 'Semua Tipe', 'Basic' => 'Basic', 'Pro' => 'Pro', 'Business' => 'Business']" />

        <x-nawasara-ui::filter-dropdown label="Status" model="status" :items="['all' => 'Semua Status', 'active' => 'Active', 'inactive' => 'Inactive']" />

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
            @if ($licenseType && $licenseType !== 'all')
                <x-nawasara-ui::filter-chip label="Type: {{ $licenseType }}" model="licenseType" />
            @endif
            @if ($status && $status !== 'all')
                <x-nawasara-ui::filter-chip label="Status: {{ ucfirst($status) }}" model="status" />
            @endif
        </x-slot:chips>
    </x-nawasara-ui::filter-bar>

    <x-nawasara-ui::table :headers="['Email', 'Name', 'License Type', 'Status', 'Last Login', '30D Meetings', 'Total Minutes']" :title="'Zoom Users (' . $users->total() . ' users)'">
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
                        <span
                            class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold
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
                            $statusBadge =
                                $user->status === 'active'
                                    ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300'
                                    : 'bg-gray-100 text-gray-800 dark:bg-neutral-700 dark:text-neutral-300';
                        @endphp
                        <span
                            class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold {{ $statusBadge }}">
                            {{ ucfirst($user->status) }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-neutral-300">
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
                        <x-nawasara-ui::empty-state
                            icon="lucide-users"
                            title="Belum ada Zoom user"
                            description="User yang ter-provisi di Zoom workspace akan auto-sync dan muncul di sini."
                            inline />
                    </td>
                </tr>
            @endforelse
        </x-slot:table>
    </x-nawasara-ui::table>

    <div class="mt-6">
        {{ $users->links() }}
    </div>
</div>
