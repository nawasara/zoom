# Nawasara Zoom Package

A Zoom meeting management dashboard for Nawasara: user management, meeting CRUD, recording management, and webhook integration, backed by DB-cached snapshots and queue-backed mutations.

It uses the UI components from **nawasara-ui** and follows the package architecture pattern of `nawasara-cloudflare` and `nawasara-whm`.

## Features

### P1: User Management (read-only mirror), ready

- List all users in the Kominfo Zoom account
- User detail with license type, department, last login, and total meetings
- Filter by license type and status
- License usage tracking
- Responsive table with pagination

### P1: Meeting Management (CRUD), ready

- List meetings (scheduled and past) with filters
- Search by topic
- Create a meeting: topic, start time, duration, host, password, waiting room, auto recording
- Update and delete a meeting
- Get the join link (copyable URL plus password)
- Recurring meeting support (P2)

### P2: Recording Management, partial

- List recordings per meeting or host
- Download link (cloud recording)
- Delete a recording with a retention policy
- Storage size tracking

### P2: Webhook Integration, partial

- Real-time event handling (meeting started/ended, recording ready)
- Signature verification (HMAC SHA-256)

## UI Components (nawasara-ui)

The package uses these components from `nawasara-ui`:

- `x-nawasara-ui::page.container`: page wrapper
- `x-nawasara-ui::page.title`: page title
- `x-nawasara-ui::page.actions`: action button area
- `x-nawasara-ui::button`: custom button with color and size variants
- `x-nawasara-ui::filter-bar`: search and filter UI
- `x-nawasara-ui::filter-dropdown`: filter dropdown
- `x-nawasara-ui::filter-chip`: active filter chip
- `x-nawasara-ui::table`: data table with headers
- `x-nawasara-ui::form.label`: form label
- `x-lucide-*`: icon components from Lucide

## Setup

### 1. Vault Configuration

Add the Zoom credentials to Vault:

```php
'zoom' => [
    'client_id'     => 'xxx',
    'client_secret' => 'xxx',
    'account_id'    => 'xxx',
],
```

### 2. Create a Server-to-Server OAuth App

1. Log in to the [Zoom Marketplace](https://marketplace.zoom.us)
2. Create a new app of type **Server-to-Server OAuth**
3. Set the scopes:
    - `user:read:admin`
    - `meeting:read:admin`, `meeting:write:admin`
    - `recording:read:admin`, `recording:write:admin`
4. Copy the Account ID, Client ID, and Client Secret into Vault

### 3. Run the Migration

```bash
php artisan migrate
```

### 4. Seed Permissions

```bash
php artisan db:seed --class="Nawasara\\Zoom\\Database\\Seeders\\PermissionSeeder"
```

### 5. Test the Connection

```bash
php artisan zoom:health-check
```

## Usage

### Sync Jobs

**Manual trigger:**

```bash
php artisan zoom:sync all       # Sync users, meetings, recordings
php artisan zoom:sync users     # Sync users only
php artisan zoom:sync meetings  # Sync meetings only
php artisan zoom:sync recordings # Sync recordings only
```

**Scheduled (automatic):**

- Users: every 1 hour
- Meetings: every 5 minutes
- Recordings: every 30 minutes

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

### Livewire Component Structure

The components follow the same pattern as the other packages:

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

**Index Component (page level):**

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

**Section Component (table/form):**

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

- `ZoomUser`: Zoom user snapshot
- `ZoomMeeting`: meeting snapshot
- `ZoomRecording`: recording snapshot

### Jobs

- `SyncZoomUsersJob`: hourly user sync
- `SyncZoomMeetingsJob`: 5-minute meeting sync
- `SyncZoomRecordingsJob`: 30-minute recording sync
- `CreateZoomMeetingJob`: create meeting mutation
- `UpdateZoomMeetingJob`: update meeting mutation
- `DeleteZoomMeetingJob`: delete meeting mutation

### Repositories

- `ZoomUserRepository`: user data access
- `ZoomMeetingRepository`: meeting data access
- `ZoomRecordingRepository`: recording data access

### Livewire Components

- `Users/Index`: list users
- `Meetings/Index`: list meetings
- `Meetings/Form`: create/edit meeting
- `Recordings/Index`: list recordings

## Database Tables

| Table                      | Purpose            |
| -------------------------- | ------------------ |
| `nawasara_zoom_users`      | User snapshot      |
| `nawasara_zoom_meetings`   | Meeting snapshot   |
| `nawasara_zoom_recordings` | Recording snapshot |

## Nawasara API (for other applications)

Requires [`nawasara/api`](../nawasara-api). If that package is not installed, the routes are not mounted.

Served from the local snapshot, not directly from the Zoom API. This keeps Zoom's rate-limit quota from being used up by consumers, and it means a Zoom-side outage does not propagate to the apps that display the agenda.

### Scopes

| Scope | Access |
|---|---|
| `zoom.meeting.read` | Meeting schedule: topic, time, duration, status, host, person in charge |
| `zoom.meeting.join` | **In addition** to the above: reveals `join_url` and `password` |
| `zoom.recording.read` | Recording list (metadata only) |

`join.` is deliberately separated from `read`: an app that only shows the agenda does not need to hold the keys to join a meeting. A token with the join scope can join any meeting, so grant it only to consumers that actually render a join button.

### Endpoints

| Method | Path | Query |
|---|---|---|
| GET | `/api/v1/zoom/meetings` | `q`, `host_id`, `window` (`upcoming` default, `past`, or `all`), `from`+`to`, `per_page` |
| GET | `/api/v1/zoom/meetings/{meetingId}` | |
| GET | `/api/v1/zoom/recordings` | `meeting_id`, `per_page` |

```bash
curl -H "Authorization: Bearer nws_xxx" \
  "https://nawasara.ponorogo.go.id/api/v1/zoom/meetings?window=upcoming&per_page=10"
```

### What is never returned

- **`start_url`**: under no circumstances, including with the join scope. That link starts the meeting **as the host**, so it is not join access but full control over someone else's meeting.
- **`download_url`, `play_url`, `file_url`**: direct links to the recording content. Once one of these leaves through a token, an internal meeting recording could be downloaded by anyone holding the link. Anyone who needs to watch is directed through Nawasara, where the access is logged.
- **`join_url`, `password`**: unless the token carries `zoom.meeting.join`.

## API Rate Limits (Zoom)

Zoom API rate limits: 30 req/sec (Light), 60 req/sec (Medium).

The default sync intervals already account for these limits. For a high-volume account, adjust them in `config/nawasara-zoom.php`.

## Troubleshooting

### "Credential belum lengkap"

Make sure Vault has the credentials:

```bash
php artisan vault:show
```

### "Token expired during request"

The ZoomClient refreshes the token automatically (cached for 55 minutes). If the error persists, check the server timezone.

### "Rate limit exceeded"

Increase the sync interval in the config, or reduce the page size.

## Cross-package Integration

| Package                   | Trigger                         | Action                     |
| ------------------------- | ------------------------------- | -------------------------- |
| **nawasara/notification** | Meeting started/ended (webhook) | Notify host/participant    |
| **nawasara/registry**     | Zoom user mapping               | Map Zoom user to OPD/PIC   |
| **nawasara/itop**         | Zoom user sync                  | Sync to iTop Person record |

## Roadmap

- [ ] Webhook real-time sync (P2)
- [ ] Batch meeting creation via CSV (P3)
- [ ] Meeting recording auto-retention (P2)
- [ ] Zoom Phone integration (P3)
- [ ] Zoom Webinar support (P3)

## License

MIT
