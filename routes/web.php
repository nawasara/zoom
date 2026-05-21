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

// Public webhook (no auth, signature-verified inside controller)
Route::post('nawasara-zoom/webhook', [ZoomWebhookController::class, 'handle'])
    ->middleware('web')
    ->name('nawasara-zoom.webhook');
