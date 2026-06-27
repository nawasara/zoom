<?php

namespace Nawasara\Zoom\Repositories;

use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Nawasara\Sync\Concerns\TracksLastSync;
use Nawasara\Zoom\Jobs\SyncZoomRecordingsJob;
use Nawasara\Zoom\Models\ZoomRecording;

class ZoomRecordingRepository
{
    use TracksLastSync;

    /** Dispatch a manual recordings sync (live + history backfill). */
    public function syncNow(): void
    {
        SyncZoomRecordingsJob::dispatch(triggerSource: 'manual');
        SyncZoomRecordingsJob::dispatch(payload: ['history' => true], triggerSource: 'manual');
    }

    /** When the recordings sync last succeeded. */
    public function lastSyncedAt(): ?Carbon
    {
        return $this->lastSuccessfulSyncAt('zoom', ['sync_recordings', 'sync_recordings_history']);
    }

    public function paginate(int $perPage = 25, array $filters = []): LengthAwarePaginator
    {
        $query = ZoomRecording::query();

        if (! empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (! empty($filters['meeting_id'])) {
            $query->where('meeting_id', $filters['meeting_id']);
        }

        if (! empty($filters['owner_id'])) {
            $query->where('owner_id', $filters['owner_id']);
        }

        if (! empty($filters['from']) || ! empty($filters['to'])) {
            $query->dateRange($filters['from'] ?? null, $filters['to'] ?? null);
        }

        return $query->orderBy('start_time', 'desc')->paginate($perPage);
    }

    public function find(string $recordingId): ?ZoomRecording
    {
        return ZoomRecording::where('recording_id', $recordingId)->first();
    }

    public function findById(int $id): ?ZoomRecording
    {
        return ZoomRecording::find($id);
    }

    public function completed(): mixed
    {
        return ZoomRecording::completed();
    }

    public function expiring(int $daysThreshold = 7): mixed
    {
        return ZoomRecording::expiring($daysThreshold);
    }

    public function statistics(): array
    {
        return [
            'total_recordings' => ZoomRecording::count(),
            'completed_recordings' => ZoomRecording::completed()->count(),
            'processing_recordings' => ZoomRecording::where('status', 'processing')->count(),
            'total_size_gb' => round(ZoomRecording::sum('file_size') / (1024 * 1024 * 1024), 2),
        ];
    }

    /**
     * Aggregate stats dengan shape yang konsisten dengan repository lain
     * (KeycloakUser, CloudflareZone) — single-pass query untuk efisiensi.
     *
     * Stats yang dipilih:
     * - total: jumlah recording
     * - completed: recording yang siap di-stream / di-download
     * - total_size: total file_size (untuk capacity awareness)
     * - expiring_soon: recording dalam 7 hari masa retensi (auto-delete by Zoom)
     */
    public function stats(): array
    {
        $row = ZoomRecording::query()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count")
            ->selectRaw('SUM(file_size) as total_size_bytes')
            ->first();

        // Expiring: recording yang start_time + retention_days <= 7 hari ke depan.
        // Hitung di PHP karena MySQL/sqlite expr-nya beda — tidak worth optimasi
        // sampai data >> 100k.
        $expiring = ZoomRecording::query()
            ->whereNotNull('start_time')
            ->where('status', 'completed')
            ->get(['start_time', 'retention_days'])
            ->filter(function ($r) {
                $expiresAt = $r->start_time->copy()->addDays($r->retention_days);
                return $expiresAt->isFuture() && $expiresAt->lessThanOrEqualTo(now()->addDays(7));
            })
            ->count();

        return [
            'total' => (int) ($row?->total ?? 0),
            'completed' => (int) ($row?->completed_count ?? 0),
            'total_size_bytes' => (int) ($row?->total_size_bytes ?? 0),
            'expiring_soon' => $expiring,
        ];
    }

    public function create(array $data): ZoomRecording
    {
        return ZoomRecording::create($data);
    }

    public function update(string $recordingId, array $data): ZoomRecording
    {
        $recording = $this->find($recordingId);
        $recording->update($data);
        return $recording;
    }

    public function delete(string $recordingId): bool
    {
        $recording = $this->find($recordingId);
        return $recording ? $recording->delete() : false;
    }

    public function getMeetingRecordings(string $meetingId): mixed
    {
        return ZoomRecording::where('meeting_id', $meetingId)->orderBy('start_time', 'desc');
    }

    public function getOwnerRecordings(string $ownerId): mixed
    {
        return ZoomRecording::where('owner_id', $ownerId)->orderBy('start_time', 'desc');
    }
}
