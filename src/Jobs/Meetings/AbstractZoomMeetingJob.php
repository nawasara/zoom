<?php

namespace Nawasara\Zoom\Jobs\Meetings;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Nawasara\Zoom\Models\ZoomMeeting;
use Nawasara\Zoom\Services\ZoomClient;

abstract class AbstractZoomMeetingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;

    protected function updateMeetingSync(string $meetingId, string $status, ?string $error = null): void
    {
        ZoomMeeting::where('meeting_id', $meetingId)->update([
            'sync_status' => $status,
            'sync_error' => $error,
            'last_synced_at' => now(),
        ]);
    }

    protected function logError(string $message, \Throwable $e): void
    {
        \Log::error($message.': '.$e->getMessage(), [
            'exception' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
    }
}
