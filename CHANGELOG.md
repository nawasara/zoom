# Changelog

All notable changes to `nawasara/zoom` are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.15] - 2026-07-15

### Fixed
- **Meeting time landed at creation time on Zoom instead of the scheduled time.**
  The picker emits `start_time` without seconds (`2026-07-20T14:30`); the Zoom
  API silently rejects that format and falls back to "now", so a scheduled
  meeting was created at the moment of creation. Send `start_time` with seconds
  (`2026-07-20T14:30:00`) in the meeting timezone. Verified end-to-end against
  the Zoom API (fetched back: correct WIB wall-clock).

## [0.1.14] - 2026-07-14

### Fixed
- **Fatal 500 when creating/updating/deleting a meeting.** `AbstractZoomMeetingJob`
  redeclared `public $queue`, which the `Queueable` trait already defines with a
  different default — PHP rejects the incompatible property composition with a
  fatal error, so the job class could not load and the request 500'd (the DB row
  was still created before dispatch, which is why the meeting appeared anyway).
  The queue is now set at dispatch via `->onQueue(...PRIORITY_QUEUE)` instead of
  a conflicting property; priority routing is unchanged.

## [0.1.13] - 2026-07-14

### Fixed
- **"+ Tambah PJ" now searches the Keycloak directory** instead of the Nawasara
  users table, so any Keycloak person can be set as penanggung jawab even if
  they have never logged into Nawasara. On select, a local user is provisioned
  from the Keycloak snapshot (name, username, email, `auth_type=sso`) before the
  OPD membership is created and the PJ selected.

## [0.1.12] - 2026-07-14

### Added
- **"+ Tambah PJ" flow** — when picking a meeting's penanggung jawab, operators
  can search Keycloak users who are not yet a member of any OPD and add the
  chosen one as a member of the meeting's OPD, then select them as PJ. Enforces
  one-user-one-OPD, gated by the `registry.membership.manage` permission.
- **PJ email invite** — once a meeting is live on Zoom, the penanggung jawab
  receives a full email invite (topic, date/time in WIB, duration, Zoom join
  link, passcode, agenda) via `nawasara/notification`. No-op when there is no
  PJ or no email; an email failure never fails the sync job.
- `nawasara/notification` added as a package dependency.

### Changed
- **Priority queue** — meeting create/update/delete jobs now run on a dedicated
  `zoom-priority` queue so user-initiated actions reach the Zoom API ahead of
  bulk sync. The prod realtime worker consumes
  `zoom-priority,default,notifications` in that order.

### Fixed
- **`start_time` timezone bug** — the meeting date no longer defaults to the
  creation day. The picker's wall-clock value is now interpreted in
  `Asia/Jakarta`, stored as the correct absolute UTC instant, and the timezone
  is sent to Zoom. Display and edit-prefill use a new `start_time_local`
  accessor. The job no longer overwrites `start_time` from the Zoom response
  (the root cause of the day shift).

## [0.1.11] - 2026-07-13

### Changed
- Penanggung jawab is now a real (Keycloak-sourced) user instead of the old
  manual registry PIC reference.

## [0.1.10] - 2026-06-29

### Fixed
- Move `zoomDateTimePicker` Alpine registration to `app.js` (`alpine:init`) so
  it survives `wire:navigate`.

## [0.1.9] - 2026-06-28

### Added
- Zoom meeting + user command-palette (⌘K) search providers.

## [0.1.8] - 2026-06-28

### Changed
- Assign workspace group and standardize the Indonesian menu label.

## [0.1.7] - 2026-06-28

### Fixed
- Show all meetings/recordings by default (period "Semua").

## [0.1.6] - 2026-06-28

### Added
- Last-sync badge and manual sync button on all Zoom pages.

## [0.1.5] - 2026-06-28

### Added
- Auto-sync schedule, historical backfill, and pagination.

## [0.1.4] - 2026-05-21

### Added
- Modal-based meeting CRUD, PIC relation, detail modal, and audit trail.

## [0.1.3] - 2026-05-21

### Fixed
- `paginate()` return type is `LengthAwarePaginator`, not `Paginator`.

## [0.1.2] - 2026-05-11

### Fixed
- SQL error in `PermissionSeeder` (dropped a reference to a non-existent
  `description` column).

## [0.1.1] - 2026-05-11

### Fixed
- Replace `wire:submit.prevent` with `wire:submit` in the meeting form
  (Livewire 3 silently fell back to GET).

## [0.1.0] - 2026-05-09

### Added
- Initial Packagist release — Zoom user management, meeting CRUD, recording
  management, and webhook integration with DB-cached snapshots and
  queue-backed mutations.

[0.1.12]: https://github.com/nawasara/zoom/compare/v0.1.11...v0.1.12
[0.1.11]: https://github.com/nawasara/zoom/compare/v0.1.10...v0.1.11
[0.1.10]: https://github.com/nawasara/zoom/compare/v0.1.9...v0.1.10
[0.1.9]: https://github.com/nawasara/zoom/compare/v0.1.8...v0.1.9
[0.1.8]: https://github.com/nawasara/zoom/compare/v0.1.7...v0.1.8
[0.1.7]: https://github.com/nawasara/zoom/compare/v0.1.6...v0.1.7
[0.1.6]: https://github.com/nawasara/zoom/compare/v0.1.5...v0.1.6
[0.1.5]: https://github.com/nawasara/zoom/compare/v0.1.4...v0.1.5
[0.1.4]: https://github.com/nawasara/zoom/compare/v0.1.3...v0.1.4
[0.1.3]: https://github.com/nawasara/zoom/compare/v0.1.2...v0.1.3
[0.1.2]: https://github.com/nawasara/zoom/compare/v0.1.1...v0.1.2
[0.1.1]: https://github.com/nawasara/zoom/compare/v0.1.0...v0.1.1
[0.1.0]: https://github.com/nawasara/zoom/releases/tag/v0.1.0
