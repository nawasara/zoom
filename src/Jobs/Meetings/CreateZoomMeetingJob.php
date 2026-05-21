<?php

namespace Nawasara\Zoom\Jobs\Meetings;

use Nawasara\Zoom\Models\ZoomMeeting;
use Nawasara\Zoom\Services\ZoomClient;

class CreateZoomMeetingJob extends AbstractZoomMeetingJob
{
    /**
     * @param  int  $localId  Primary key of the local placeholder ZoomMeeting
     *                        row. meeting_id (the Zoom ID) does not exist yet —
     *                        the Zoom API assigns it in handle().
     */
    public function __construct(
        protected int $localId,
        protected string $hostId,
        protected array $meetingData,
    ) {}

    public function handle(ZoomClient $client): void
    {
        $meeting = ZoomMeeting::findOrFail($this->localId);

        try {
            if (! $client->isConfigured()) {
                throw new \Exception('Zoom client not configured');
            }

            $meeting->update(['sync_status' => 'syncing']);

            $response = $client->createMeeting($this->hostId, $this->meetingData);

            // Zoom returns the meeting id as an integer; cast to string to
            // match the meeting_id column.
            $meeting->update([
                'meeting_id' => (string) $response['id'],
                'join_url' => $response['join_url'] ?? null,
                'start_time' => $response['start_time'] ?? $meeting->start_time,
                'password' => $response['password'] ?? null,
                'sync_status' => 'synced',
                'sync_error' => null,
                'last_synced_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $this->logError('Failed to create Zoom meeting', $e);

            $meeting->update([
                'sync_status' => 'failed',
                'sync_error' => $e->getMessage(),
                'last_synced_at' => now(),
            ]);

            throw $e;
        }
    }
}
