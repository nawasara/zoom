<?php

namespace Nawasara\Zoom\Jobs;

use Carbon\CarbonImmutable;
use Nawasara\Sync\Jobs\AbstractSyncJob;
use Nawasara\Zoom\Models\ZoomMeeting;
use Nawasara\Zoom\Models\ZoomUser;
use Nawasara\Zoom\Services\ZoomClient;

/**
 * Sync meetings for every active Zoom user into nawasara_zoom_meetings.
 *
 * Two layers:
 *   - Live/near-term: scheduled + upcoming + previous_meetings (the /meetings
 *     endpoint). Fast, runs every cycle.
 *   - Historical backfill: the Reports API (/report/users/{id}/meetings),
 *     looped month-by-month back `history_months`, which captures EVERY past
 *     meeting the user hosted — including instant meetings the /meetings
 *     endpoint never returns. This is what populates "riwayat yang telah lalu".
 *
 * The historical pass only runs when payload['history'] is true (so the
 * frequent meeting-sync cron stays cheap; history is a slower, occasional
 * dispatch). Reports API needs the report:read:admin scope — if it's missing
 * Zoom 4xxs and we log + continue rather than fail the whole job.
 */
class SyncZoomMeetingsJob extends AbstractSyncJob
{
    public int $timeout = 600;

    protected function service(): string
    {
        return 'zoom';
    }

    protected function action(): string
    {
        return ! empty($this->payload['history']) ? 'sync_meetings_history' : 'sync_meetings';
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
        $synced = 0;
        $historical = 0;

        foreach ($users as $user) {
            $synced += $this->syncUserMeetings($client, $user->user_id);

            if (! empty($this->payload['history'])) {
                $historical += $this->backfillHistory($client, $user->user_id);
            }
        }

        return ['users' => $users->count(), 'meetings' => $synced, 'historical' => $historical];
    }

    /**
     * Current/near-term meetings via the /meetings endpoint.
     */
    protected function syncUserMeetings(ZoomClient $client, string $userId): int
    {
        $count = 0;
        foreach (['scheduled', 'upcoming', 'previous_meetings'] as $type) {
            try {
                $remote = $client->getMeetings($userId, $type)['meetings'] ?? [];
                foreach ($remote as $m) {
                    $this->syncMeeting($m);
                    $count++;
                }
            } catch (\Throwable $e) {
                \Log::warning("Zoom meetings sync ({$type}) failed for {$userId}: ".$e->getMessage());
            }
        }

        return $count;
    }

    /**
     * Historical meetings via the Reports API, month by month back
     * `history_months`. Zoom caps each report query to a one-month span.
     */
    protected function backfillHistory(ZoomClient $client, string $userId): int
    {
        $months = max(1, (int) config('nawasara-zoom.history_months', 6));
        $count = 0;
        $cursor = CarbonImmutable::now();

        for ($i = 0; $i < $months; $i++) {
            $to = $cursor;
            $from = $cursor->subMonth();
            try {
                $remote = $client->getUserMeetingsReport(
                    $userId,
                    $from->toDateString(),
                    $to->toDateString(),
                )['meetings'] ?? [];

                foreach ($remote as $m) {
                    $this->syncMeeting($m);
                    $count++;
                }
            } catch (\Throwable $e) {
                // Most commonly a missing report:read:admin scope. Log once per
                // user-month and keep going — never fail the whole sync on it.
                \Log::warning("Zoom report backfill failed for {$userId} ({$from->toDateString()}..{$to->toDateString()}): ".$e->getMessage());
            }
            $cursor = $from;
        }

        return $count;
    }

    /**
     * Upsert one meeting. Handles both the /meetings shape and the Reports
     * shape (which uses slightly different keys: 'duration' in minutes,
     * 'start_time', 'end_time', no 'settings').
     */
    protected function syncMeeting(array $remote): void
    {
        if (empty($remote['id'])) {
            return;
        }

        ZoomMeeting::updateOrCreate(
            ['meeting_id' => $remote['id']],
            [
                'host_id' => $remote['host_id'] ?? null,
                'topic' => $remote['topic'] ?? '(tanpa judul)',
                'start_time' => $remote['start_time'] ?? null,
                'duration' => $remote['duration'] ?? 0,
                'timezone' => $remote['timezone'] ?? null,
                'password' => $remote['password'] ?? null,
                'agenda' => $remote['agenda'] ?? null,
                'status' => $remote['status'] ?? 'ended',
                'type' => $remote['type'] ?? 2,
                'join_url' => $remote['join_url'] ?? null,
                'auto_recording' => $remote['settings']['auto_recording'] ?? 'none',
                'waiting_room' => $remote['settings']['waiting_room_settings']['is_waiting_room_enabled'] ?? false,
                'zoom_created_at' => $remote['created_at'] ?? ($remote['start_time'] ?? null),
                'zoom_updated_at' => $remote['updated_at'] ?? null,
                'sync_status' => 'synced',
                'sync_error' => null,
                'last_synced_at' => now(),
            ]
        );
    }
}
