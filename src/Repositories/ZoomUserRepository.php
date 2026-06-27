<?php

namespace Nawasara\Zoom\Repositories;

use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Nawasara\Sync\Concerns\TracksLastSync;
use Nawasara\Zoom\Jobs\SyncZoomUsersJob;
use Nawasara\Zoom\Models\ZoomUser;

class ZoomUserRepository
{
    use TracksLastSync;

    /** Dispatch a manual users sync. */
    public function syncNow(): void
    {
        SyncZoomUsersJob::dispatch(triggerSource: 'manual');
    }

    /** When the users sync last succeeded. */
    public function lastSyncedAt(): ?Carbon
    {
        return $this->lastSuccessfulSyncAt('zoom', 'sync_users');
    }

    public function paginate(int $perPage = 25, array $filters = []): LengthAwarePaginator
    {
        $query = ZoomUser::query();

        if (! empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (! empty($filters['license_type'])) {
            $query->licenseType($filters['license_type']);
        }

        if (! empty($filters['status'])) {
            $query->status($filters['status']);
        }

        return $query->orderBy('last_login_at', 'desc')->paginate($perPage);
    }

    public function find(string $userId): ?ZoomUser
    {
        return ZoomUser::where('user_id', $userId)->first();
    }

    public function findByEmail(string $email): ?ZoomUser
    {
        return ZoomUser::where('email', $email)->first();
    }

    public function active(): mixed
    {
        return ZoomUser::where('status', 'active');
    }

    public function statistics(): array
    {
        return [
            'total_users' => ZoomUser::count(),
            'active_users' => ZoomUser::where('status', 'active')->count(),
            'licensed_users' => ZoomUser::where('user_type', 2)->count(),
            'inactive_users' => ZoomUser::where('status', 'inactive')->count(),
        ];
    }

    public function create(array $data): ZoomUser
    {
        return ZoomUser::create($data);
    }

    public function update(string $userId, array $data): ZoomUser
    {
        $user = $this->find($userId);
        $user->update($data);
        return $user;
    }

    public function delete(string $userId): bool
    {
        $user = $this->find($userId);
        return $user ? $user->delete() : false;
    }
}
