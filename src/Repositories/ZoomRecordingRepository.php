<?php

namespace Nawasara\Zoom\Repositories;

use Nawasara\Zoom\Models\ZoomRecording;
use Illuminate\Pagination\Paginator;

class ZoomRecordingRepository
{
    public function paginate(int $perPage = 25, array $filters = []): Paginator
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
