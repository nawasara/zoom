<?php

use Illuminate\Support\Facades\Route;
use Nawasara\Zoom\Http\Controllers\ZoomWebhookController;
use Nawasara\Zoom\Livewire\User\Index as UserIndex;
use Nawasara\Zoom\Livewire\Meeting\Index as MeetingIndex;
use Nawasara\Zoom\Livewire\Meeting\Create as MeetingCreate;
use Nawasara\Zoom\Livewire\Meeting\Edit as MeetingEdit;
use Nawasara\Zoom\Livewire\Recording\Index as RecordingIndex;
use Spatie\Permission\Middleware\PermissionMiddleware;

Route::middleware(['web', 'auth'])->prefix('nawasara-zoom')->group(function () {
    Route::get('users', UserIndex::class)
        ->middleware(PermissionMiddleware::using('zoom.user.view'))
        ->name('nawasara-zoom.user.index');

    Route::get('meetings', MeetingIndex::class)
        ->middleware(PermissionMiddleware::using('zoom.meeting.view'))
        ->name('nawasara-zoom.meeting.index');

    Route::get('meetings/create', MeetingCreate::class)
        ->middleware(PermissionMiddleware::using('zoom.meeting.create'))
        ->name('nawasara-zoom.meeting.create');

    Route::get('meetings/{meeting}/edit', MeetingEdit::class)
        ->middleware(PermissionMiddleware::using('zoom.meeting.update'))
        ->name('nawasara-zoom.meeting.edit');

    Route::get('recordings', RecordingIndex::class)
        ->middleware(PermissionMiddleware::using('zoom.recording.view'))
        ->name('nawasara-zoom.recording.index');
});

// Public webhook (no auth, signature-verified inside controller)
Route::post('nawasara-zoom/webhook', [ZoomWebhookController::class, 'handle'])
    ->middleware('web')
    ->name('nawasara-zoom.webhook');
