<div>
    <x-nawasara-ui::sync-info-bar
        :lastSyncedAt="$this->lastSyncedAt"
        neverSyncedMessage='Belum pernah di-sync. Klik tombol sync untuk menarik meeting + riwayat dari Zoom.' />

    @php
        // Single-select derived filter (upcoming/past are mutually exclusive
        // date scopes). filter-panel treats it as single because typeFilter
        // is NOT in the :multiple prop list.
        $typeOptions = ['upcoming' => 'Upcoming', 'past' => 'Past'];
    @endphp

    {{-- Page header — title + description left, time-window + primary
         action ("+ Buat Meeting") right. Both title and the create button
         moved here from index.blade.php so they share a row with the
         time-window's reactive state. --}}
    <x-nawasara-ui::page-header
        title="Zoom Meetings"
        description="Schedule, manage, and track all your Zoom meetings"
        :count="$meetings->total().' meetings'">
        <x-nawasara-ui::time-window :window="$window" :from="$from" :to="$to"
            :presets="['all' => 'Semua', 'today' => 'Hari ini', '7d' => '7 hari', '30d' => '30 hari']" />

        @can('zoom.meeting.create')
            <x-nawasara-ui::button color="success" wire:click="$dispatch('openCreateMeeting')">
                <x-slot:icon><x-lucide-plus class="size-4" /></x-slot:icon>
                Buat Meeting
            </x-nawasara-ui::button>
        @endcan
    </x-nawasara-ui::page-header>

    {{-- Toolbar — Type filter + search + reset + export. Time window
         lifted to the page header above; this row narrows within the
         active period. --}}
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

            <x-nawasara-ui::search-input model="search" placeholder="Cari topic atau agenda..." />

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

                <x-nawasara-ui::icon-button icon="refresh-cw" tooltip="Sync meeting + riwayat dari Zoom"
                    wire:click="syncNow" loadingTarget="syncNow" />

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

    {{-- Title omitted; <x-page-header> renders it above. --}}
    <x-nawasara-ui::table
        stickyLast
        :headers="['Topic', 'Host', 'Start Time', 'Duration', 'Status', 'Recording', '']">
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
                        {{ $meeting->start_time_local?->format('d M Y H:i') ?? '-' }}{{ $meeting->start_time_local ? ' WIB' : '' }}
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
                            ['type' => 'click', 'label' => 'Detail', 'wire:click' => 'openDetail('.$meeting->id.')', 'modal' => 'zoom-meeting-detail', 'icon' => 'lucide-eye', 'permission' => 'zoom.meeting.view'],
                            ['type' => 'click', 'label' => 'Edit', 'wire:click' => 'openEdit('.$meeting->id.')', 'modal' => 'zoom-meeting-form', 'icon' => 'lucide-pencil', 'permission' => 'zoom.meeting.update'],
                            ['type' => 'delete', 'label' => 'Delete', 'permission' => 'zoom.meeting.delete', 'name' => $meeting->topic],
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

    {{-- Delete confirmation — dropdown 'delete' item dispatches modal-delete,
         this modal's Delete button dispatches confirm-delete, handled by the
         Table component's #[On('confirm-delete')] delete() method. --}}
    <x-nawasara-ui::modal-confirm-delete />

    {{-- Detail modal — opened by the dropdown 'Detail' item via openDetail().
         Consistent with other packages (e.g. proxmox VM detail). --}}
    <x-nawasara-ui::modal id="zoom-meeting-detail" maxWidth="2xl"
        :title="$this->detail?->topic ?? 'Meeting Detail'">
        @if ($this->detail)
            @php
                $m = $this->detail;
                $syncBadge = match ($m->sync_status) {
                    'synced' => ['success', 'Synced'],
                    'pending' => ['warning', 'Pending'],
                    'syncing' => ['info', 'Syncing'],
                    'failed' => ['danger', 'Failed'],
                    default => ['neutral', $m->sync_status ?? '-'],
                };
            @endphp

            <div class="flex items-center gap-2 mb-4">
                <x-nawasara-ui::badge :color="$syncBadge[0]">{{ $syncBadge[1] }}</x-nawasara-ui::badge>
                @if ($m->meeting_id)
                    <span class="text-xs text-gray-500 dark:text-neutral-400">Meeting ID: {{ $m->meeting_id }}</span>
                @endif
            </div>

            <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2 text-sm">
                <div>
                    <dt class="text-gray-500 dark:text-neutral-400">Host</dt>
                    <dd class="mt-0.5 text-gray-900 dark:text-neutral-100">
                        {{ $m->host?->full_name ?? '-' }}
                        @if ($m->host?->email)
                            <span class="text-gray-500 dark:text-neutral-400">({{ $m->host->email }})</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-neutral-400">Penanggung Jawab</dt>
                    <dd class="mt-0.5 text-gray-900 dark:text-neutral-100">
                        @if ($m->penanggungJawab)
                            @php $pjProfile = $m->pjProfile(); @endphp
                            {{ $pjProfile->name }}
                            @if ($pjProfile->nip)
                                <span class="text-gray-500 dark:text-neutral-400">— {{ $pjProfile->nip }}</span>
                            @endif
                        @else
                            <span class="text-gray-400">-</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-neutral-400">Start Time</dt>
                    <dd class="mt-0.5 text-gray-900 dark:text-neutral-100">
                        {{ optional($m->start_time_local)->format('d M Y, H:i') ?? '-' }}{{ $m->start_time_local ? ' WIB' : '' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-neutral-400">Duration</dt>
                    <dd class="mt-0.5 text-gray-900 dark:text-neutral-100">{{ $m->duration }} menit</dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-neutral-400">Status</dt>
                    <dd class="mt-0.5 text-gray-900 dark:text-neutral-100">{{ $m->status }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-neutral-400">Auto Recording</dt>
                    <dd class="mt-0.5 text-gray-900 dark:text-neutral-100">{{ $m->auto_recording }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-neutral-400">Waiting Room</dt>
                    <dd class="mt-0.5 text-gray-900 dark:text-neutral-100">{{ $m->waiting_room ? 'Aktif' : 'Nonaktif' }}</dd>
                </div>
                {{-- Meeting password — copy-able. Zoom join password, needed by
                     participants; shown plainly here for the operator. --}}
                <div x-data="{ copied: false }">
                    <dt class="text-gray-500 dark:text-neutral-400">Password</dt>
                    <dd class="mt-0.5">
                        @if ($m->password)
                            <button type="button"
                                @click="$clipboard('{{ $m->password }}'); copied = true; setTimeout(() => copied = false, 1500)"
                                class="inline-flex items-center gap-1.5 font-mono text-gray-900 dark:text-neutral-100 hover:text-emerald-700 dark:hover:text-emerald-400">
                                <span>{{ $m->password }}</span>
                                <x-lucide-copy class="size-3.5" x-show="! copied" />
                                <x-lucide-check class="size-3.5 text-emerald-600" x-show="copied" x-cloak />
                            </button>
                        @else
                            <span class="text-gray-400">-</span>
                        @endif
                    </dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-gray-500 dark:text-neutral-400">Join URL</dt>
                    <dd class="mt-0.5">
                        @if ($m->join_url)
                            <a href="{{ $m->join_url }}" target="_blank" rel="noopener noreferrer"
                                class="text-emerald-700 hover:underline dark:text-emerald-400 break-all">
                                {{ $m->join_url }}
                            </a>
                        @else
                            <span class="text-gray-400">-</span>
                        @endif
                    </dd>
                </div>
                @if ($m->agenda)
                    <div class="sm:col-span-2">
                        <dt class="text-gray-500 dark:text-neutral-400">Agenda</dt>
                        <dd class="mt-0.5 text-gray-900 dark:text-neutral-100 whitespace-pre-line">{{ $m->agenda }}</dd>
                    </div>
                @endif
                @if ($m->sync_error)
                    <div class="sm:col-span-2">
                        <dt class="text-rose-500">Sync Error</dt>
                        <dd class="mt-0.5 text-rose-600 dark:text-rose-400 text-xs">{{ $m->sync_error }}</dd>
                    </div>
                @endif
            </dl>

            <x-slot:footer>
                @can('zoom.meeting.update')
                    {{-- Edit from detail: close this modal, then openEdit opens
                         the form modal (its own dispatch handles that). --}}
                    <x-nawasara-ui::button color="primary"
                        wire:click="openEdit({{ $m->id }})"
                        x-on:click="closeModal('zoom-meeting-detail')">
                        <x-slot:icon><x-lucide-pencil class="size-4" /></x-slot:icon>
                        Edit
                    </x-nawasara-ui::button>
                @endcan
                <x-nawasara-ui::button color="neutral" variant="outline"
                    x-on:click="closeModal('zoom-meeting-detail')">
                    Tutup
                </x-nawasara-ui::button>
            </x-slot:footer>
        @endif
    </x-nawasara-ui::modal>

    {{-- Create / Edit form modal — opened by the header "Buat Meeting" button
         (openCreateMeeting event) and the dropdown "Edit" item (openEdit).
         Same modal-as-form pattern as nawasara-notification templates. --}}
    @php
        $fieldClass = 'py-3 px-4 block w-full border border-gray-300 rounded-md text-sm transition-all duration-200 focus:border-transparent focus:ring-2 focus:ring-emerald-700/80 focus:!border-transparent outline-none dark:bg-neutral-900 dark:border-gray-800 text-gray-900 dark:text-neutral-100';
    @endphp
    <x-nawasara-ui::modal id="zoom-meeting-form" maxWidth="2xl"
        :title="$editingId ? 'Edit Meeting' : 'Buat Meeting'">
        <form wire:submit="save" id="zoom-meeting-form-el" class="space-y-5">
            {{-- Host --}}
            <div>
                <x-nawasara-ui::form.label for="formHostId">Host <span class="text-red-500">*</span></x-nawasara-ui::form.label>
                <select id="formHostId" wire:model="formHostId" class="{{ $fieldClass }}">
                    <option value="">Select a host</option>
                    @foreach ($hosts as $host)
                        <option value="{{ $host->user_id }}">{{ $host->full_name }} ({{ $host->email }})</option>
                    @endforeach
                </select>
                @error('formHostId')
                    <span class="mt-1 block text-xs text-red-600">{{ $message }}</span>
                @enderror
            </div>

            {{-- Penanggung Jawab (optional) — pick an OPD first, then a member
                 (user) of that OPD sourced from registry memberships. The OPD
                 itself isn't stored on the meeting; it's derived from the PJ's
                 membership when displaying. --}}
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <x-nawasara-ui::form.select
                        label="OPD"
                        wire:model.live="formOpdId"
                        :options="$opds->pluck('name', 'id')->all()"
                        placeholder="Pilih OPD"
                        hint="Opsional — pilih OPD untuk memfilter penanggung jawab" />
                </div>
                <div>
                    <x-nawasara-ui::form.select
                        label="Penanggung Jawab"
                        wire:model="formPjUserId"
                        :options="$pjCandidates"
                        :placeholder="$formOpdId ? 'Pilih Penanggung Jawab' : 'Pilih OPD dulu'"
                        :disabled="! $formOpdId"
                        hint="Opsional" />
                    @error('formPjUserId')
                        <span class="mt-1 block text-xs text-red-600">{{ $message }}</span>
                    @enderror
                    @if ($formOpdId)
                        @can('registry.membership.manage')
                            <button type="button"
                                x-on:click="$dispatch('open-modal', { id: 'zoom-add-pj', loading: false })"
                                class="mt-1.5 inline-flex items-center gap-1 text-xs font-medium text-emerald-600 hover:text-emerald-700 dark:text-emerald-400 dark:hover:text-emerald-300">
                                <x-lucide-user-plus class="size-3.5" />
                                Tambah PJ baru ke OPD ini
                            </button>
                        @endcan
                    @endif
                </div>
            </div>

            {{-- Topic --}}
            <div>
                <x-nawasara-ui::form.label for="formTopic">Topic <span class="text-red-500">*</span></x-nawasara-ui::form.label>
                <x-nawasara-ui::form.input id="formTopic" wire:model="formTopic" placeholder="Meeting topic" />
                @error('formTopic')
                    <span class="mt-1 block text-xs text-red-600">{{ $message }}</span>
                @enderror
            </div>

            {{-- Agenda --}}
            <div>
                <x-nawasara-ui::form.label for="formAgenda">Agenda</x-nawasara-ui::form.label>
                <x-nawasara-ui::form.textarea id="formAgenda" wire:model="formAgenda" :rows="3"
                    placeholder="Meeting agenda" />
            </div>

            {{-- Start Time & Duration --}}
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <x-nawasara-ui::form.label for="formStartTime">Start Time <span class="text-red-500">*</span></x-nawasara-ui::form.label>
                    {{-- flatpickr hides the real input and writes to a shadow
                         field; Alpine pushes the value back via $wire.set().
                         wire:key forces a fresh node when the modal reopens so
                         flatpickr re-inits with the right defaultDate. --}}
                    <div wire:ignore wire:key="zoom-start-{{ $editingId ?? 'new' }}" x-data="zoomDateTimePicker">
                        <input type="text" x-ref="input" id="formStartTime"
                            value="{{ $formStartTime }}" placeholder="Pilih tanggal & jam"
                            class="{{ $fieldClass }}">
                    </div>
                    @error('formStartTime')
                        <span class="mt-1 block text-xs text-red-600">{{ $message }}</span>
                    @enderror
                </div>
                <div>
                    <x-nawasara-ui::form.label for="formDuration">Duration (minutes) <span class="text-red-500">*</span></x-nawasara-ui::form.label>
                    <x-nawasara-ui::form.input type="number" id="formDuration" wire:model="formDuration" min="15" max="1440" />
                    @error('formDuration')
                        <span class="mt-1 block text-xs text-red-600">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            {{-- Password --}}
            <div>
                <x-nawasara-ui::form.label for="formPassword">Meeting Password</x-nawasara-ui::form.label>
                <x-nawasara-ui::form.input type="text" id="formPassword" wire:model="formPassword"
                    placeholder="Leave blank for auto-generated" />
            </div>

            {{-- Settings --}}
            <div class="pt-4 border-t border-gray-200 dark:border-neutral-700">
                <h3 class="mb-3 text-sm font-semibold text-gray-900 dark:text-neutral-100">Settings</h3>
                <div class="space-y-2.5">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" wire:model="formAutoRecording"
                            class="rounded border-gray-300 text-emerald-700 shadow-sm focus:ring-emerald-600 dark:bg-neutral-900 dark:border-neutral-700">
                        <span class="text-sm text-gray-700 dark:text-gray-300">Auto-record meeting to cloud</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" wire:model="formWaitingRoom"
                            class="rounded border-gray-300 text-emerald-700 shadow-sm focus:ring-emerald-600 dark:bg-neutral-900 dark:border-neutral-700">
                        <span class="text-sm text-gray-700 dark:text-gray-300">Enable waiting room</span>
                    </label>
                </div>
            </div>
        </form>

        <x-slot:footer>
            <x-nawasara-ui::button color="neutral" variant="outline"
                x-on:click="$dispatch('close-modal', 'zoom-meeting-form')">Batal</x-nawasara-ui::button>
            <x-nawasara-ui::button type="submit" form="zoom-meeting-form-el" color="success">
                {{ $editingId ? 'Update Meeting' : 'Create Meeting' }}
            </x-nawasara-ui::button>
        </x-slot:footer>
    </x-nawasara-ui::modal>

    {{-- Add a Keycloak user (not yet in any OPD) as this OPD's PJ. Sits OUTSIDE
         the meeting form so its own inputs don't submit the meeting. --}}
    <x-nawasara-ui::modal id="zoom-add-pj" maxWidth="lg"
        title="Tambah Penanggung Jawab ke OPD">
        <div class="space-y-4">
            <p class="text-sm text-neutral-600 dark:text-neutral-300">
                Cari user Keycloak yang <strong>belum tergabung di OPD manapun</strong>.
                User yang dipilih akan ditambahkan sebagai anggota OPD ini lalu
                langsung dipilih sebagai penanggung jawab.
            </p>

            <div>
                <x-nawasara-ui::form.input
                    wire:model.live.debounce.400ms="pjSearch"
                    placeholder="Ketik nama, username, atau email (min. 2 huruf)…"
                    autocomplete="off" />
            </div>

            <div class="max-h-72 overflow-y-auto rounded-lg border border-neutral-200 dark:border-neutral-700">
                @php $pjResults = $this->unassignedUserResults(); @endphp
                @forelse ($pjResults as $idx => $u)
                    <button type="button"
                        wire:key="unassigned-{{ $u['kc_username'] }}"
                        wire:click="addPjToOpd({{ $idx }})"
                        class="flex w-full items-center justify-between gap-3 border-b border-neutral-100 px-3 py-2.5 text-left last:border-0 hover:bg-emerald-50 dark:border-neutral-800 dark:hover:bg-emerald-900/20">
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-medium text-neutral-800 dark:text-neutral-100">{{ $u['name'] }}</span>
                            @if ($u['nip'])
                                <span class="block truncate text-xs text-neutral-500 dark:text-neutral-400">NIP {{ $u['nip'] }}</span>
                            @elseif ($u['email'])
                                <span class="block truncate text-xs text-neutral-500 dark:text-neutral-400">{{ $u['email'] }}</span>
                            @endif
                        </span>
                        <span class="inline-flex shrink-0 items-center gap-1 text-xs font-medium text-emerald-600 dark:text-emerald-400">
                            <x-lucide-plus class="size-3.5" /> Tambah
                        </span>
                    </button>
                @empty
                    <div class="px-3 py-6 text-center text-sm text-neutral-500 dark:text-neutral-400">
                        @if (mb_strlen(trim($pjSearch)) < 2)
                            Ketik minimal 2 huruf untuk mencari.
                        @else
                            Tidak ada user Keycloak yang cocok (atau semua sudah tergabung di OPD).
                        @endif
                    </div>
                @endforelse
            </div>
        </div>

        <x-slot:footer>
            <x-nawasara-ui::button color="neutral" variant="outline"
                x-on:click="$dispatch('close-modal', 'zoom-add-pj')">Tutup</x-nawasara-ui::button>
        </x-slot:footer>
    </x-nawasara-ui::modal>

    {{-- Alpine.data('zoomDateTimePicker', ...) lives in resources/js/app.js
         (alpine:init), NOT a @push('script') block — @push scripts don't re-run
         after wire:navigate, which left it "not defined" when reaching this page
         by navigation. See reference_alpine_magic_wire_navigate. --}}
</div>
