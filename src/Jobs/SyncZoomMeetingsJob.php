<?php

namespace Nawasara\Zoom\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Nawasara\Zoom\Models\ZoomMeeting;
use Nawasara\Zoom\Models\ZoomUser;
use Nawasara\Zoom\Services\ZoomClient;

class SyncZoomMeetingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300;

    public function handle(ZoomClient $client): void
    {
        if (! $client->isConfigured()) {
            return;
        }

        try {
            // Get all active users
            $users = ZoomUser::where('status', 'active')->get();

            foreach ($users as $user) {
                $this->syncUserMeetings($client, $user->user_id);
            }
        } catch (\Throwable $e) {
            ZoomMeeting::query()->update([
                'sync_status' => 'failed',
                'sync_error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    protected function syncUserMeetings(ZoomClient $client, string $userId): void
    {
        try {
            // Get scheduled meetings
            $response = $client->getMeetings($userId, 'scheduled');
            $remoteMeetings = $response['meetings'] ?? [];

            foreach ($remoteMeetings as $remote) {
                $this->syncMeeting($remote);
            }

            // Get upcoming meetings
            $response = $client->getMeetings($userId, 'upcoming');
            $remoteMeetings = $response['meetings'] ?? [];

            foreach ($remoteMeetings as $remote) {
                $this->syncMeeting($remote);
            }

            // Get past meetings
            $response = $client->getMeetings($userId, 'previous_meetings');
            $remoteMeetings = $response['meetings'] ?? [];

            foreach ($remoteMeetings as $remote) {
                $this->syncMeeting($remote);
            }
        } catch (\Throwable $e) {
            \Log::error("Failed to sync meetings for user {$userId}: ".$e->getMessage());
        }
    }

    protected function syncMeeting(array $remote): void
    {
        $data = [
            'host_id' => $remote['host_id'],
            'topic' => $remote['topic'],
            'start_time' => $remote['start_time'] ?? null,
            'duration' => $remote['duration'] ?? 0,
            'timezone' => $remote['timezone'] ?? null,
            'password' => $remote['password'] ?? null,
            'agenda' => $remote['agenda'] ?? null,
            'status' => $remote['status'] ?? 'not_started',
            'type' => $remote['type'] ?? 2,
            'join_url' => $remote['join_url'] ?? null,
            'auto_recording' => $remote['settings']['auto_recording'] ?? 'none',
            'waiting_room' => $remote['settings']['waiting_room_settings']['is_waiting_room_enabled'] ?? false,
            'zoom_created_at' => $remote['created_at'] ?? null,
            'zoom_updated_at' => $remote['updated_at'] ?? null,
        ];

        ZoomMeeting::updateOrCreate(
            ['meeting_id' => $remote['id']],
            $data
        );
    }
}
