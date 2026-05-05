<?php

namespace Nawasara\Zoom\Jobs\Meetings;

use Nawasara\Zoom\Services\ZoomClient;

class UpdateZoomMeetingJob extends AbstractZoomMeetingJob
{
    public function __construct(
        protected string $meetingId,
        protected array $meetingData,
    ) {}

    public function handle(ZoomClient $client): void
    {
        try {
            if (! $client->isConfigured()) {
                throw new \Exception('Zoom client not configured');
            }

            $this->updateMeetingSync($this->meetingId, 'syncing');

            $client->updateMeeting($this->meetingId, $this->meetingData);

            $this->updateMeetingSync($this->meetingId, 'synced');
        } catch (\Throwable $e) {
            $this->logError('Failed to update Zoom meeting', $e);
            $this->updateMeetingSync($this->meetingId, 'failed', $e->getMessage());

            throw $e;
        }
    }
}
