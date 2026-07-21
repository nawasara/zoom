<?php

namespace Nawasara\Zoom\Livewire\Meeting\Section;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Nawasara\Keycloak\Models\KeycloakUser;
use Nawasara\Keycloak\Support\KeycloakProfile;
use Nawasara\Registry\Models\Membership;
use Nawasara\Registry\Models\Opd;
use Nawasara\Toaster\Concerns\HasToaster;
use Nawasara\Ui\Livewire\Concerns\HasExport;
use Nawasara\Ui\Livewire\Concerns\HasTimeWindow;
use Nawasara\Zoom\Jobs\Meetings\AbstractZoomMeetingJob;
use Nawasara\Zoom\Jobs\Meetings\CreateZoomMeetingJob;
use Nawasara\Zoom\Jobs\Meetings\DeleteZoomMeetingJob;
use Nawasara\Zoom\Jobs\Meetings\UpdateZoomMeetingJob;
use Nawasara\Zoom\Models\ZoomMeeting;
use Nawasara\Zoom\Repositories\ZoomMeetingRepository;
use Nawasara\Zoom\Repositories\ZoomUserRepository;

class Table extends Component
{
    use HasExport;
    use HasToaster;
    // The list is paginated (repo->paginate + $meetings->links()), so this
    // component needs WithPagination — it provides resetPage().
    use WithPagination;
    // HasTimeWindow declares $window / $from / $to, plus resolveTimeWindow()
    // that we use below to translate the active preset into bounds for the
    // repository's existing dateRange filter.
    use HasTimeWindow;

    #[Url]
    public string $search = '';

    #[Url]
    public string $hostId = '';

    /**
     * Single-select derived filter: 'upcoming' | 'past' | '' (all).
     */
    #[Url]
    public string $typeFilter = '';

    public array $selected = [];
    public bool $selectAll = false;

    /** Detail modal — id of the meeting currently shown, null = closed. */
    public ?int $detailId = null;

    // ─── Create/Edit form modal state ───────────────────────
    // The meeting form lives in this component as a modal, NOT a separate
    // page — same pattern as nawasara-notification's template table.
    public ?int $editingId = null;
    public string $formHostId = '';
    // Penanggung jawab: user picks an OPD, then a member (user) of that OPD.
    // The OPD itself isn't stored on the meeting — it's derived from the PJ's
    // membership when displaying.
    public ?int $formOpdId = null;
    public ?int $formPjUserId = null;
    public string $formTopic = '';
    public ?string $formStartTime = null;

    // "+ Tambah PJ" flow: search Keycloak users who aren't yet in any OPD and
    // add the chosen one as a member of the selected OPD (one user = one OPD).
    public string $pjSearch = '';
    public int $formDuration = 60;
    public string $formPassword = '';
    public string $formAgenda = '';
    public bool $formAutoRecording = false;
    public bool $formWaitingRoom = false;

    // ─── Detail modal ───────────────────────────────────────

    public function openDetail(int $id): void
    {
        $this->detailId = $id;
        $this->dispatch('modal-open:zoom-meeting-detail');
    }

    public function closeDetail(): void
    {
        $this->detailId = null;
        $this->dispatch('modal-close:zoom-meeting-detail');
    }

    /**
     * The meeting shown in the detail modal, relations eager-loaded.
     */
    public function getDetailProperty(): ?ZoomMeeting
    {
        if (! $this->detailId) {
            return null;
        }

        return ZoomMeeting::with(['host', 'penanggungJawab'])->find($this->detailId);
    }

    // ─── Create / Edit ──────────────────────────────────────

    #[On('openCreateMeeting')]
    public function openCreate(): void
    {
        Gate::authorize('zoom.meeting.create');
        $this->resetForm();
        $this->dispatch('modal-open:zoom-meeting-form');
    }

    public function openEdit(int $id): void
    {
        Gate::authorize('zoom.meeting.update');

        $meeting = ZoomMeeting::find($id);
        if (! $meeting) {
            return;
        }

        $this->editingId = $meeting->id;
        $this->formHostId = $meeting->host_id ?? '';
        $this->formTopic = $meeting->topic ?? '';
        // Pre-fill the picker with the local (WIB) wall-clock, matching how the
        // user originally entered it — not the UTC instant stored in the column.
        $this->formStartTime = $meeting->start_time_local?->format('Y-m-d\TH:i');
        $this->formDuration = $meeting->duration ?? 60;
        $this->formPassword = $meeting->password ?? '';
        $this->formAgenda = $meeting->agenda ?? '';
        $this->formAutoRecording = $meeting->auto_recording !== 'none';
        $this->formWaitingRoom = (bool) $meeting->waiting_room;
        $this->formPjUserId = $meeting->pj_user_id;
        // Derive the OPD (for the OPD → member dropdown) from the PJ's
        // membership — the meeting doesn't store opd_id itself.
        $this->formOpdId = $meeting->pj_user_id
            ? Membership::where('user_id', $meeting->pj_user_id)->where('aktif', true)->value('opd_id')
            : null;
        $this->resetErrorBag();

        $this->dispatch('modal-open:zoom-meeting-form');
    }

    /**
     * Clearing/changing the OPD filter drops a PJ that is no longer a member
     * of the selected OPD.
     */
    public function updatedFormOpdId(): void
    {
        if ($this->formPjUserId
            && Membership::where('user_id', $this->formPjUserId)
                ->where('opd_id', $this->formOpdId)
                ->where('aktif', true)
                ->doesntExist()) {
            $this->formPjUserId = null;
        }
    }

    public function save(): void
    {
        Gate::authorize($this->editingId ? 'zoom.meeting.update' : 'zoom.meeting.create');

        $this->validate([
            'formHostId' => 'required|string',
            'formTopic' => 'required|string|max:255',
            'formStartTime' => 'required|date_format:Y-m-d\TH:i',
            'formDuration' => 'required|integer|min:15|max:1440',
            'formPjUserId' => 'nullable|integer|exists:users,id',
        ]);

        // Meetings are scheduled in local time. The picker gives a wall-clock
        // string (Y-m-d\TH:i) with no offset; Zoom interprets start_time in the
        // `timezone` we send, so ALWAYS send Asia/Jakarta — otherwise Zoom
        // treats it as GMT and the meeting shifts (and could roll back a day).
        $tz = config('nawasara-zoom.timezone', 'Asia/Jakarta');

        // Interpret the wall-clock picker value (Y-m-d\TH:i, no seconds) in the
        // meeting timezone. Keep both a local-tz copy (for the Zoom payload) and
        // a UTC copy (for the DB, which stores absolute instants).
        $startLocal = \Carbon\Carbon::createFromFormat('Y-m-d\TH:i', $this->formStartTime, $tz);
        $startAt = $startLocal->copy()->utc();

        // Zoom API payload — pj_user_id is local-only, not sent to Zoom.
        // start_time MUST include seconds (yyyy-MM-ddTHH:mm:ss). The picker emits
        // it WITHOUT seconds ("2026-07-20T14:30"); Zoom silently rejects that
        // format and falls back to "now" — the meeting then lands at creation
        // time instead of the scheduled time (the reported bug). Send the local
        // wall-clock WITH seconds alongside timezone=Asia/Jakarta.
        $data = [
            'topic' => $this->formTopic,
            'type' => 2, // scheduled meeting
            'start_time' => $startLocal->format('Y-m-d\TH:i:s'),
            'timezone' => $tz,
            'duration' => $this->formDuration,
            'password' => $this->formPassword,
            'agenda' => $this->formAgenda,
            'settings' => [
                'auto_recording' => $this->formAutoRecording ? 'cloud' : 'none',
                'waiting_room_settings' => [
                    'is_waiting_room_enabled' => $this->formWaitingRoom,
                ],
            ],
        ];

        if ($this->editingId) {
            $meeting = ZoomMeeting::findOrFail($this->editingId);
            $meeting->update([
                'pj_user_id' => $this->formPjUserId,
                'topic'      => $this->formTopic,
                'start_time' => $startAt,
                'timezone'   => $tz,
                'duration'   => $this->formDuration,
            ]);
            UpdateZoomMeetingJob::dispatch($meeting->meeting_id, $data)
                ->onQueue(AbstractZoomMeetingJob::PRIORITY_QUEUE);
            $this->alert('success', 'Perubahan meeting disimpan — sinkronisasi ke Zoom berjalan di latar belakang.');
        } else {
            // Local placeholder row — meeting_id is null until the Zoom API
            // assigns one. Pass the DB primary key to the job.
            $meeting = ZoomMeeting::create([
                'host_id' => $this->formHostId,
                'pj_user_id' => $this->formPjUserId,
                'topic' => $this->formTopic,
                'start_time' => $startAt,
                'timezone' => $tz,
                'duration' => $this->formDuration,
                'sync_status' => 'pending',
            ]);

            CreateZoomMeetingJob::dispatch($meeting->id, $this->formHostId, $data)
                ->onQueue(AbstractZoomMeetingJob::PRIORITY_QUEUE);
            $this->alert('success', 'Meeting dibuat — sinkronisasi ke Zoom berjalan di latar belakang.');
        }

        $this->dispatch('modal-close:zoom-meeting-form');
        $this->resetForm();
        $this->resetPage();
    }

    protected function resetForm(): void
    {
        $this->editingId = null;
        $this->formHostId = '';
        $this->formOpdId = null;
        $this->formPjUserId = null;
        $this->formTopic = '';
        $this->formStartTime = null;
        $this->formDuration = 60;
        $this->formPassword = '';
        $this->formAgenda = '';
        $this->formAutoRecording = false;
        $this->formWaitingRoom = false;
        $this->resetErrorBag();
    }

    // ─── Delete ─────────────────────────────────────────────

    /**
     * Delete a meeting. The local row goes immediately; the Zoom-side
     * deletion is queued. A meeting that was never synced (no meeting_id)
     * has nothing on Zoom to delete, so the job is skipped.
     */
    #[On('confirm-delete')]
    public function delete($id): void
    {
        Gate::authorize('zoom.meeting.delete');

        $meeting = ZoomMeeting::findOrFail($id);
        $meetingId = $meeting->meeting_id;

        $meeting->delete();

        if ($meetingId) {
            DeleteZoomMeetingJob::dispatch($meetingId)
                ->onQueue(AbstractZoomMeetingJob::PRIORITY_QUEUE);
        }

        $this->dispatch('close-modal', id: 'modalConfirmDelete');
        $this->resetPage();
        $this->alert('success', 'Meeting dihapus.');
    }

    // ─── List + filters ─────────────────────────────────────

    /**
     * Zoom data is small + infrequent, so default to showing everything
     * rather than a rolling 7-day window that hides most meetings (and
     * confuses against the all-time KPI cards). Users can still narrow.
     */
    protected function defaultTimeWindow(): string
    {
        return 'all';
    }

    /** Human-readable "last synced" for the sync-info-bar, or null if never. */
    #[\Livewire\Attributes\Computed]
    public function lastSyncedAt(): ?string
    {
        $when = (new ZoomMeetingRepository())->lastSyncedAt();

        return $when?->diffForHumans();
    }

    /** Manual sync button — dispatches live + history backfill. */
    public function syncNow(): void
    {
        Gate::authorize('zoom.meeting.view');

        (new ZoomMeetingRepository())->syncNow();
        $this->alert('info', 'Sinkronisasi meeting (termasuk riwayat) berjalan di latar belakang. Refresh sebentar lagi.');
    }

    public function render()
    {
        $repo = new ZoomMeetingRepository();
        $meetings = $repo->paginate(25, $this->buildFilters());

        return view('nawasara-zoom::livewire.pages.meeting.section.table', [
            'meetings' => $meetings,
            'hosts' => (new ZoomUserRepository())->active()->get(),
            'opds' => Opd::orderBy('name')->get(['id', 'name']),
            'pjCandidates' => $this->pjCandidates(),
        ]);
    }

    /**
     * Penanggung-jawab candidates for the currently-selected OPD: active
     * members of that OPD, keyed by user id → resolved display name.
     *
     * @return array<int, string>
     */
    protected function pjCandidates(): array
    {
        if (! $this->formOpdId) {
            return [];
        }

        return Membership::where('opd_id', $this->formOpdId)
            ->where('aktif', true)
            ->with('user')
            ->get()
            ->filter(fn ($m) => $m->user !== null)
            ->mapWithKeys(fn ($m) => [$m->user_id => KeycloakProfile::for($m->user)->name])
            ->all();
    }

    /**
     * Search the Keycloak directory (snapshot) for people to set as PJ. This
     * searches Keycloak itself, NOT the Nawasara users table — so a person who
     * has never logged into Nawasara still shows up. On select they are
     * provisioned as a local user (see addPjToOpd). Keycloak users who already
     * map to a Nawasara user that is a member of some OPD are excluded, to keep
     * one-user-one-OPD. Keyed by Keycloak username. Returns up to 15 candidates.
     *
     * @return array<int, array{kc_username:string, name:string, nip:?string, email:?string}>
     */
    public function unassignedUserResults(): array
    {
        $term = trim($this->pjSearch);
        if (mb_strlen($term) < 2) {
            return [];
        }

        $userModel = config('auth.providers.users.model');

        // Usernames/emails of Nawasara users already assigned to an OPD — used
        // to hide Keycloak people who are effectively already in a dinas.
        $assignedUserIds = Membership::pluck('user_id');
        $assignedUsers   = $userModel::whereIn('id', $assignedUserIds)->get(['username', 'email']);
        $assignedUsernames = $assignedUsers->pluck('username')->filter()->map(fn ($u) => mb_strtolower($u))->all();
        $assignedEmails    = $assignedUsers->pluck('email')->filter()->map(fn ($e) => mb_strtolower($e))->all();

        return KeycloakUser::query()
            ->search($term)
            ->where('enabled', true)
            ->limit(40)
            ->get()
            ->reject(function ($kc) use ($assignedUsernames, $assignedEmails) {
                $u = mb_strtolower((string) $kc->username);
                $e = mb_strtolower((string) $kc->email);

                return ($u !== '' && in_array($u, $assignedUsernames, true))
                    || ($e !== '' && in_array($e, $assignedEmails, true));
            })
            ->take(15)
            ->map(fn ($kc) => [
                'kc_username' => $kc->username,
                'name'        => $kc->full_name ?: ($kc->username ?? '—'),
                'nip'         => $kc->nip,
                'email'       => $kc->email,
            ])
            ->values()
            ->all();
    }

    /**
     * Set a Keycloak person as this OPD's PJ. The person need NOT be an existing
     * Nawasara user: if there is no local user row for them yet, one is
     * provisioned from the Keycloak snapshot (same shape as SSO auto-provision).
     * They are then added as an active member of the selected OPD and selected
     * as PJ. Guarded by permission; enforces one-user-one-OPD.
     *
     * Takes the row index into the current search results (resolved server-side
     * to the Keycloak username) rather than passing the username through the DOM
     * — see CLAUDE.md 13.f.
     */
    public function addPjToOpd(int $index): void
    {
        Gate::authorize('registry.membership.manage');

        if (! $this->formOpdId) {
            $this->alert('error', 'Pilih OPD terlebih dahulu.');
            return;
        }

        $kcUsername = $this->unassignedUserResults()[$index]['kc_username'] ?? null;
        if (! $kcUsername) {
            $this->alert('error', 'Pilihan tidak valid, coba cari ulang.');
            return;
        }

        $kc = KeycloakUser::where('username', $kcUsername)->first();
        if (! $kc) {
            $this->alert('error', 'User Keycloak tidak ditemukan.');
            return;
        }

        $userModel = config('auth.providers.users.model');

        $user = DB::transaction(function () use ($kc, $userModel) {
            // Find an existing local user by username, else email.
            $user = $userModel::where('username', $kc->username)->first()
                ?? ($kc->email ? $userModel::where('email', $kc->email)->first() : null);

            // Provision from the Keycloak snapshot if they have never been a
            // Nawasara user. Mirrors SsoController auto-provision (dummy password
            // to satisfy NOT NULL; login always happens via SSO).
            if (! $user) {
                $user = $userModel::create([
                    'name'      => $kc->full_name ?: ($kc->username ?? 'SSO User'),
                    'username'  => $kc->username,
                    'email'     => $kc->email ?: ($kc->username.'@sso.local'),
                    'password'  => bcrypt(\Illuminate\Support\Str::random(40)),
                    'auth_type' => 'sso',
                ]);
            }

            return $user;
        });

        // Fail-safe: never create a second membership for a user.
        if (Membership::where('user_id', $user->id)->exists()) {
            $this->alert('error', 'User sudah terdaftar di OPD lain.');
            return;
        }

        Membership::create([
            'user_id' => $user->id,
            'opd_id'  => $this->formOpdId,
            'aktif'   => true,
        ]);

        $this->formPjUserId = $user->id;
        $this->pjSearch = '';
        $this->dispatch('modal-close:zoom-add-pj');
        $this->alert('success', 'Penanggung jawab ditambahkan ke OPD & dipilih.');
    }

    /**
     * Translate component state into the shape ZoomMeetingRepository expects.
     *
     * @return array<string, mixed>
     */
    protected function buildFilters(): array
    {
        [$from, $to] = $this->resolveTimeWindow();

        // Send the FULL datetime, not toDateString(). resolveTimeWindow()
        // returns endOfDay() (…23:59:59) as the upper bound on purpose;
        // truncating to Y-m-d collapses it to 00:00:00 and filters out every
        // meeting later than midnight today.
        return [
            'search' => $this->search,
            'host_id' => $this->hostId,
            'type' => $this->typeFilter,
            'from' => $from?->toDateTimeString(),
            'to' => $to?->toDateTimeString(),
        ];
    }

    public function resetFilters()
    {
        $this->reset('search', 'hostId', 'typeFilter', 'window', 'from', 'to');
        $this->resetPage();
    }

    public function updatedSelectAll()
    {
        $repo = new ZoomMeetingRepository();
        $meetings = $repo->paginate(1000, $this->buildFilters());

        $this->selected = $this->selectAll
            ? $meetings->pluck('id')->map(fn ($id) => (string) $id)->toArray()
            : [];
    }

    public function resetSelection()
    {
        $this->selected = [];
        $this->selectAll = false;
    }

    /**
     * Export filename base — timestamp + extension appended by HasExport.
     */
    protected function exportFilename(): string
    {
        return 'zoom-meetings';
    }

    /**
     * Export FULL meetings dataset (no filter) per spec. Capped to keep xlsx
     * generation bounded; meetings table can grow large in active orgs.
     */
    protected function exportData(): iterable
    {
        return ZoomMeeting::query()
            ->with('host')
            ->orderByDesc('start_time')
            ->limit(10000)
            ->get()
            ->map(fn (ZoomMeeting $m) => [
                'Meeting ID' => $m->meeting_id,
                'Topic' => $m->topic,
                'Host' => $m->host?->full_name,
                'Host Email' => $m->host?->email,
                'Start Time' => optional($m->start_time)->format('Y-m-d H:i'),
                'Duration (min)' => $m->duration,
                'Status' => $m->status,
                'Can Record' => $m->can_record ? 'Yes' : 'No',
                'Type' => $m->type,
                'Timezone' => $m->timezone,
                'Agenda' => $m->agenda,
                'Last Synced' => optional($m->last_synced_at)->format('Y-m-d H:i'),
            ]);
    }
}
