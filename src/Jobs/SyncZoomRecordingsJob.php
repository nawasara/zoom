<?php

namespace Nawasara\Zoom\Jobs;

use Carbon\CarbonImmutable;
use Nawasara\Sync\Jobs\AbstractSyncJob;
use Nawasara\Zoom\Models\ZoomRecording;
use Nawasara\Zoom\Models\ZoomUser;
use Nawasara\Zoom\Services\ZoomClient;

/**
 * Sync cloud recordings for every active Zoom user into
 * nawasara_zoom_recordings.
 *
 * Like meetings, the recordings endpoint defaults to a recent window, so when
 * payload['history'] is set we loop month-by-month back `history_months` with
 * explicit from/to to backfill the archive. Extends AbstractSyncJob for
 * tracking in the Sync Jobs UI.
 */
class SyncZoomRecordingsJob extends AbstractSyncJob
{
    public int $timeout = 600;

    protected function service(): string
    {
        return 'zoom';
    }

    protected function action(): string
    {
        return ! empty($this->payload['history']) ? 'sync_recordings_history' : 'sync_recordings';
    }

    protected function targetType(): ?string
    {
        return null;
    }

    protected function targetId(): ?string
    {
        return null;
    }

    protected function execute(): array
    {
        $client = app(ZoomClient::class);

        if (! $client->isConfigured()) {
            throw new \RuntimeException('Zoom Vault credentials are not configured.');
        }

        $users = ZoomUser::where('status', 'active')->get();
        $count = 0;

        foreach ($users as $user) {
            if (! empty($this->payload['history'])) {
                $count += $this->syncWindowedRecordings($client, $user->user_id);
            } else {
                $count += $this->syncUserRecordings($client, $user->user_id);
            }
        }

        return ['users' => $users->count(), 'recordings' => $count];
    }

    /** Recent recordings (endpoint default window). */
    protected function syncUserRecordings(ZoomClient $client, string $userId, array $params = []): int
    {
        $count = 0;
        try {
            $meetings = $client->getRecordings($userId, $params)['meetings'] ?? [];
            foreach ($meetings as $meeting) {
                foreach (($meeting['recording_files'] ?? []) as $file) {
                    $this->syncRecording($file, $meeting['id'], $userId);
                    $count++;
                }
            }
        } catch (\Throwable $e) {
            \Log::warning("Zoom recordings sync failed for {$userId}: ".$e->getMessage());
        }

        return $count;
    }

    /** Historical recordings, month by month back history_months. */
    protected function syncWindowedRecordings(ZoomClient $client, string $userId): int
    {
        $months = max(1, (int) config('nawasara-zoom.history_months', 6));
        $count = 0;
        $cursor = CarbonImmutable::now();

        for ($i = 0; $i < $months; $i++) {
            $to = $cursor;
            $from = $cursor->subMonth();
            $count += $this->syncUserRecordings($client, $userId, [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ]);
            $cursor = $from;
        }

        return $count;
    }

    protected function syncRecording(array $remote, string $meetingId, string $ownerId): void
    {
        if (empty($remote['id'])) {
            return;
        }

        ZoomRecording::updateOrCreate(
            ['recording_id' => $remote['id']],
            [
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
                'status' => $remote['status'] ?? 'completed',
                'recording_type' => $remote['recording_type'] ?? null,
                'zoom_created_at' => $remote['recording_start'] ?? null,
                'sync_status' => 'synced',
                'sync_error' => null,
                'last_synced_at' => now(),
            ]
        );
    }
}
