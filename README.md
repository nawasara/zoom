# Nawasara Zoom Package

Zoom meeting management dashboard untuk Nawasara — user management, meeting CRUD, recording management, dan webhook integration dengan DB-cached snapshots dan queue-backed mutations.

Menggunakan komponen UI dari **nawasara-ui** dan mengikuti pola package architecture seperti `nawasara-cloudflare` dan `nawasara-whm`.

## Fitur

### ✅ P1 — User Management (Read-only Mirror)

- List semua user di account Zoom Kominfo
- Detail user dengan license type, department, last login, total meetings
- Filter by license type dan status
- License usage tracking
- Responsive table dengan pagination

### ✅ P1 — Meeting Management (CRUD)

- List meeting (scheduled + past) dengan filter
- Search by topic
- Create meeting — topic, start time, duration, host, password, waiting room, recording auto
- Update dan delete meeting
- Get join link (copyable URL + password)
- Support recurring meeting (P2)

### 🟡 P2 — Recording Management

- List recording per meeting/host
- Download link (cloud recording)
- Delete recording dengan retention policy
- Storage size tracking

### 🟡 P2 — Webhook Integration

- Real-time event handling (meeting started/ended, recording ready)
- Signature verification (HMAC SHA-256)

## UI Components (nawasara-ui)

Package menggunakan komponen-komponen dari `nawasara-ui`:

- `x-nawasara-ui::page.container` — Page wrapper
- `x-nawasara-ui::page.title` — Page title
- `x-nawasara-ui::page.actions` — Action buttons area
- `x-nawasara-ui::button` — Custom button dengan color/size variants
- `x-nawasara-ui::filter-bar` — Search + filter UI
- `x-nawasara-ui::filter-dropdown` — Filter dropdown
- `x-nawasara-ui::filter-chip` — Active filter chip
- `x-nawasara-ui::table` — Data table dengan headers
- `x-nawasara-ui::form.label` — Form label
- `x-lucide-*` — Icon components dari Lucide

## Setup

### 1. Vault Configuration

Tambah credential Zoom ke Vault:

```php
'zoom' => [
    'client_id'     => 'xxx',
    'client_secret' => 'xxx',
    'account_id'    => 'xxx',
],
```

### 2. Create Server-to-Server OAuth App

1. Login ke [Zoom Marketplace](https://marketplace.zoom.us)
2. Create aplikasi baru dengan type **Server-to-Server OAuth**
3. Set scopes:
    - `user:read:admin`
    - `meeting:read:admin`, `meeting:write:admin`
    - `recording:read:admin`, `recording:write:admin`
4. Copy Account ID, Client ID, Client Secret ke Vault

### 3. Run Migration

```bash
php artisan migrate
```

### 4. Seed Permissions

```bash
php artisan db:seed --class="Nawasara\\Zoom\\Database\\Seeders\\PermissionSeeder"
```

### 5. Test Connection

```bash
php artisan zoom:health-check
```

## Penggunaan

### Sync Jobs

**Manual trigger:**

```bash
php artisan zoom:sync all       # Sync users, meetings, recordings
php artisan zoom:sync users     # Sync users only
php artisan zoom:sync meetings  # Sync meetings only
php artisan zoom:sync recordings # Sync recordings only
```

**Scheduled (automatic):**

- Users: setiap 1 jam
- Meetings: setiap 5 menit
- Recordings: setiap 30 menit

### Permissions

```
zoom.view
zoom.user.view
zoom.meeting.view
zoom.meeting.create
zoom.meeting.update
zoom.meeting.delete
zoom.recording.view
zoom.recording.download
zoom.recording.delete
zoom.sync.execute
```

### Livewire Components Structure

Components mengikuti pola yang konsisten dengan packages lain:

```
src/Livewire/
├── Users/
│   ├── Index.php                    # Main page component
│   └── Section/
│       └── Table.php               # Table section component
├── Meetings/
│   ├── Index.php                   # Main page component
│   ├── Create.php                  # Create page component
│   ├── Edit.php                    # Edit page component
│   └── Section/
│       ├── Table.php              # Meetings table component
│       └── Form.php               # Create/edit form component
└── Recordings/
    ├── Index.php                   # Main page component
    └── Section/
        └── Table.php              # Recordings table component
```

### View Structure

```
resources/views/livewire/pages/
├── users/
│   ├── index.blade.php             # Users page layout
│   └── section/
│       └── table.blade.php         # Users table with filters
├── meetings/
│   ├── index.blade.php             # Meetings list page
│   ├── create.blade.php            # Create meeting page
│   ├── edit.blade.php              # Edit meeting page
│   └── section/
│       ├── table.blade.php         # Meetings table with filters
│       └── form.blade.php          # Meeting form component
└── recordings/
    ├── index.blade.php             # Recordings page layout
    └── section/
        └── table.blade.php         # Recordings table with filters
```

### Key Livewire Pattern

**Index Component (Page Level):**

```php
class Index extends Component
{
    public function render()
    {
        return view('nawasara-zoom::livewire.pages.users.index')
            ->layout('nawasara-ui::components.layouts.app');
    }
}
```

**Section Component (Table/Form):**

```php
class Table extends Component
{
    #[Url]
    public string $search = '';

    public function render()
    {
        $repo = new ZoomUserRepository();
        $users = $repo->paginate(25, ['search' => $this->search]);

        return view('nawasara-zoom::livewire.pages.users.section.table', [
            'users' => $users,
        ]);
    }
}
```

## Database

### Models

- `ZoomUser` — snapshot user Zoom
- `ZoomMeeting` — snapshot meeting
- `ZoomRecording` — snapshot recording

### Jobs

- `SyncZoomUsersJob` — hourly user sync
- `SyncZoomMeetingsJob` — 5-minute meeting sync
- `SyncZoomRecordingsJob` — 30-minute recording sync
- `CreateZoomMeetingJob` — create meeting mutation
- `UpdateZoomMeetingJob` — update meeting mutation
- `DeleteZoomMeetingJob` — delete meeting mutation

### Repositories

- `ZoomUserRepository` — user data access
- `ZoomMeetingRepository` — meeting data access
- `ZoomRecordingRepository` — recording data access

### Livewire Components

- `Users/Index` — list users
- `Meetings/Index` — list meetings
- `Meetings/Form` — create/edit meeting
- `Recordings/Index` — list recordings

## Database Tables

| Table                      | Purpose            |
| -------------------------- | ------------------ |
| `nawasara_zoom_users`      | User snapshot      |
| `nawasara_zoom_meetings`   | Meeting snapshot   |
| `nawasara_zoom_recordings` | Recording snapshot |

## API Rate Limits

Zoom API rate limits: 30 req/sec (Light), 60 req/sec (Medium)

Default sync intervals sudah account untuk limits ini. Untuk high-volume account, adjust di `config/nawasara-zoom.php`.

## Troubleshooting

### "Credential belum lengkap"

Pastikan Vault sudah punya credential:

```bash
php artisan vault:show
```

### "Token expired during request"

ZoomClient automatically refresh token (cached untuk 55 menit). Jika error persist, check timezone server.

### "Rate limit exceeded"

Increase sync interval di config atau reduce page size.

## Cross-package Integration

| Package                   | Trigger                         | Action                     |
| ------------------------- | ------------------------------- | -------------------------- |
| **nawasara/notification** | Meeting started/ended (webhook) | Notify host/participant    |
| **nawasara/registry**     | Zoom user mapping               | Map Zoom user ↔ OPD/PIC    |
| **nawasara/itop**         | Zoom user sync                  | Sync ke iTop Person record |

## Roadmap

- [ ] Webhook real-time sync (P2)
- [ ] Batch meeting creation via CSV (P3)
- [ ] Meeting recordings auto-retention (P2)
- [ ] Integration dengan Zoom Phone (P3)
- [ ] Zoom Webinar support (P3)

## License

MIT
