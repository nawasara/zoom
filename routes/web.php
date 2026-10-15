<?php

use Illuminate\Support\Facades\Route;
use Nawasara\Zoom\Http\Controllers\ZoomWebhookController;
use Nawasara\Zoom\Livewire\User\Index as UserIndex;
use Nawasara\Zoom\Livewire\Meeting\Index as MeetingIndex;
use Nawasara\Zoom\Livewire\Recording\Index as RecordingIndex;
use Spatie\Permission\Middleware\PermissionMiddleware;

Route::middleware(['web', 'auth'])->prefix('nawasara-zoom')->group(function () {
    Route::get('users', UserIndex::class)
        ->middleware(PermissionMiddleware::using('zoom.user.view'))
        ->name('nawasara-zoom.user.index');

    // Meetings — single index page. Create/Edit are modals inside the table
    // component (openCreateMeeting / openEdit), not separate routes.
    Route::get('meetings', MeetingIndex::class)
        ->middleware(PermissionMiddleware::using('zoom.meeting.view'))
        ->name('nawasara-zoom.meeting.index');

    Route::get('recordings', RecordingIndex::class)
        ->middleware(PermissionMiddleware::using('zoom.recording.view'))
        ->name('nawasara-zoom.recording.index');
});

// Public webhook (no auth, signature-verified inside controller).
//
// Audit keamanan 15 Oktober 2026 (R3): dulu memakai grup `web`, sehingga
// VerifyCsrfToken menolak setiap kiriman Zoom (419) dan webhook tidak pernah
// sampai ke controller. Kini tanpa grup `web` (tanpa CSRF dan sesi), cukup
// throttle per IP. Keaslian kiriman dijaga tanda tangan HMAC + jendela waktu.
Route::post('nawasara-zoom/webhook', [ZoomWebhookController::class, 'handle'])
    ->middleware('throttle:120,1')
    ->name('nawasara-zoom.webhook');
