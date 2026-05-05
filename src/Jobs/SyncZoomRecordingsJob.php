<?php

namespace Nawasara\Zoom\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Nawasara\Zoom\Models\ZoomRecording;
use Nawasara\Zoom\Models\ZoomUser;
use Nawasara\Zoom\Services\ZoomClient;

class SyncZoomRecordingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 600;

    public function handle(ZoomClient $client): void
    {
        if (! $client->isConfigured()) {
            return;
        }

        try {
            // Get all active users
            $users = ZoomUser::where('status', 'active')->get();

            foreach ($users as $user) {
                $this->syncUserRecordings($client, $user->user_id);
            }
        } catch (\Throwable $e) {
            ZoomRecording::query()->update([
                'sync_status' => 'failed',
                'sync_error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    protected function syncUserRecordings(ZoomClient $client, string $userId): void
    {
        try {
            $response = $client->getRecordings($userId);
            $remoteMeetings = $response['meetings'] ?? [];

            foreach ($remoteMeetings as $meeting) {
                $recordingFiles = $meeting['recording_files'] ?? [];

                foreach ($recordingFiles as $remote) {
                    $this->syncRecording($remote, $meeting['id'], $userId);
                }
            }
        } catch (\Throwable $e) {
            \Log::error("Failed to sync recordings for user {$userId}: ".$e->getMessage());
        }
    }

    protected function syncRecording(array $remote, string $meetingId, string $ownerId): void
    {
        $data = [
            'meeting_id' => $meetingId,
            'owner_id' => $ownerId,
            'topic' => $remote['meeting_topic'] ?? '',
            'start_time' => $remote['recording_start'] ?? null,
            'duration' => $remote['recording_duration'] ?? 0,
            'file_type' => $remote['file_type'] ?? null,
            'file_size' => $remote['file_size'] ?? 0,
            'file_url' => $remote['content_url'] ?? null,
            'play_url' => $remote['play_url'] ?? null,
            'download_url' => $remote['download_url'] ?? null,
            'status' => $remote['status'] ?? 'processing',
            'recording_type' => $remote['recording_type'] ?? null,
            'zoom_created_at' => $remote['recording_start'] ?? null,
        ];

        ZoomRecording::updateOrCreate(
            ['recording_id' => $remote['id']],
            $data
        );
    }
}
