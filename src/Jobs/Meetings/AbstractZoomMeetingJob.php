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

    /**
     * Meeting create/update/delete are user-initiated and must reach Zoom fast,
     * so they run on a dedicated priority queue that bulk sync jobs never share.
     *
     * This is NOT a `public $queue` property: the Queueable trait already
     * declares `$queue`, and redeclaring it with a different default is a fatal
     * "incompatible property composition" error. Instead each job assigns the
     * queue after construction via this helper — child jobs call it from their
     * own constructor (they don't chain parent::__construct).
     */
    public const PRIORITY_QUEUE = 'zoom-priority';

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
