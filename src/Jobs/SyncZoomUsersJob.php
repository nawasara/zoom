<?php

namespace Nawasara\Zoom\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Nawasara\Zoom\Models\ZoomUser;
use Nawasara\Zoom\Services\ZoomClient;

class SyncZoomUsersJob implements ShouldQueue
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
            $response = $client->getUsers();
            $remoteUsers = $response['users'] ?? [];

            foreach ($remoteUsers as $remote) {
                $this->syncUser($remote);
            }

            // Mark as synced
            ZoomUser::query()->update([
                'sync_status' => 'synced',
                'sync_error' => null,
                'last_synced_at' => now(),
            ]);
        } catch (\Throwable $e) {
            ZoomUser::query()->update([
                'sync_status' => 'failed',
                'sync_error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    protected function syncUser(array $remote): void
    {
        $data = [
            'user_id' => $remote['id'],
            'email' => $remote['email'],
            'first_name' => $remote['first_name'] ?? '',
            'last_name' => $remote['last_name'] ?? '',
            'user_type' => $remote['type'],
            'license_type' => $remote['plan_united_type'] ?? null,
            'status' => $remote['status'] ?? 'active',
            'last_login_at' => $remote['last_login_time'] ?? null,
            'zoom_created_at' => $remote['created_at'] ?? null,
        ];

        ZoomUser::updateOrCreate(
            ['user_id' => $remote['id']],
            $data
        );
    }
}
