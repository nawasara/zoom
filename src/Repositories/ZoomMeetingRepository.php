<?php

namespace Nawasara\Zoom\Repositories;

use Nawasara\Zoom\Models\ZoomMeeting;
use Illuminate\Pagination\Paginator;

class ZoomMeetingRepository
{
    public function paginate(int $perPage = 25, array $filters = []): Paginator
    {
        $query = ZoomMeeting::query();

        if (! empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (! empty($filters['host_id'])) {
            $query->host($filters['host_id']);
        }

        if (! empty($filters['from']) || ! empty($filters['to'])) {
            $query->dateRange($filters['from'] ?? null, $filters['to'] ?? null);
        }

        if ($filters['type'] === 'upcoming') {
            $query->upcoming();
        } elseif ($filters['type'] === 'past') {
            $query->past();
        }

        return $query->orderBy('start_time', 'desc')->paginate($perPage);
    }

    public function find(string $meetingId): ?ZoomMeeting
    {
        return ZoomMeeting::where('meeting_id', $meetingId)->first();
    }

    public function findById(int $id): ?ZoomMeeting
    {
        return ZoomMeeting::find($id);
    }

    public function upcoming(): mixed
    {
        return ZoomMeeting::upcoming();
    }

    public function past(): mixed
    {
        return ZoomMeeting::past();
    }

    public function statistics(): array
    {
        return [
            'total_meetings' => ZoomMeeting::count(),
            'upcoming_meetings' => ZoomMeeting::upcoming()->count(),
            'finished_meetings' => ZoomMeeting::where('status', 'finished')->count(),
            'with_recording' => ZoomMeeting::where('auto_recording', '!=', 'none')->count(),
        ];
    }

    public function create(array $data): ZoomMeeting
    {
        return ZoomMeeting::create($data);
    }

    public function update(string $meetingId, array $data): ZoomMeeting
    {
        $meeting = $this->find($meetingId);
        $meeting->update($data);
        return $meeting;
    }

    public function delete(string $meetingId): bool
    {
        $meeting = $this->find($meetingId);
        return $meeting ? $meeting->delete() : false;
    }

    public function getByHost(string $hostId): mixed
    {
        return ZoomMeeting::where('host_id', $hostId)->orderBy('start_time', 'desc');
    }
}
