<?php

namespace Nawasara\Zoom\Jobs\Meetings;

use Nawasara\Zoom\Services\ZoomClient;

class DeleteZoomMeetingJob extends AbstractZoomMeetingJob
{
    public function __construct(
        protected string $meetingId,
        protected ?string $occurrenceId = null,
    ) {}

    public function handle(ZoomClient $client): void
    {
        try {
            if (! $client->isConfigured()) {
                throw new \Exception('Zoom client not configured');
            }

            $this->updateMeetingSync($this->meetingId, 'syncing');

            $client->deleteMeeting($this->meetingId, $this->occurrenceId);

            // Delete from DB
            // ZoomMeeting::where('meeting_id', $this->meetingId)->delete();

            $this->updateMeetingSync($this->meetingId, 'synced');
        } catch (\Throwable $e) {
            $this->logError('Failed to delete Zoom meeting', $e);
            $this->updateMeetingSync($this->meetingId, 'failed', $e->getMessage());

            throw $e;
        }
    }
}
