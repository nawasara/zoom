<?php

use Illuminate\Support\Facades\Route;
use Nawasara\Zoom\Http\Api\MeetingController;
use Nawasara\Zoom\Http\Api\RecordingController;

/*
|--------------------------------------------------------------------------
| Zoom API routes
|--------------------------------------------------------------------------
| Di-mount oleh ZoomServiceProvider di prefix /api/v1/zoom dengan middleware
| group: api + api.auth + api.log.
|
| Read-only. Dilayani dari snapshot lokal, bukan Zoom API langsung, supaya
| kuota rate limit Zoom tidak habis dipakai konsumen.
|
| `zoom.meeting.join` sengaja dipisah dari `zoom.meeting.read`: yang pertama
| membuka join_url + password, yang kedua hanya jadwal. Aplikasi yang
| menampilkan agenda tidak perlu ikut memegang kunci masuk rapat.
*/

Route::middleware('scope:zoom.meeting.read')->group(function () {
    Route::get('/meetings', [MeetingController::class, 'index'])->name('meetings.index');
    Route::get('/meetings/{meetingId}', [MeetingController::class, 'show'])->name('meetings.show');
});

Route::middleware('scope:zoom.recording.read')->group(function () {
    Route::get('/recordings', [RecordingController::class, 'index'])->name('recordings.index');
});
