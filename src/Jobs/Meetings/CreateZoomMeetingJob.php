<?php

namespace Nawasara\Zoom\Jobs\Meetings;

use Nawasara\Zoom\Models\ZoomMeeting;
use Nawasara\Zoom\Services\ZoomClient;

class CreateZoomMeetingJob extends AbstractZoomMeetingJob
{
    public function __construct(
        protected string $meetingId,
        protected string $hostId,
        protected array $meetingData,
    ) {}

    public function handle(ZoomClient $client): void
    {
        try {
            if (! $client->isConfigured()) {
                throw new \Exception('Zoom client not configured');
            }

            $this->updateMeetingSync($this->meetingId, 'syncing');

            $response = $client->createMeeting($this->hostId, $this->meetingData);

            $meeting = ZoomMeeting::where('meeting_id', $this->meetingId)->first();
            if ($meeting) {
                $meeting->update([
                    'meeting_id' => $response['id'],
                    'join_url' => $response['join_url'] ?? null,
                    'start_time' => $response['start_time'] ?? null,
                    'password' => $response['password'] ?? null,
                ]);
            }

            $this->updateMeetingSync($this->meetingId, 'synced');
        } catch (\Throwable $e) {
            $this->logError('Failed to create Zoom meeting', $e);
            $this->updateMeetingSync($this->meetingId, 'failed', $e->getMessage());

            throw $e;
        }
    }
}
