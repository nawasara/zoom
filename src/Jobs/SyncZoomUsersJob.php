<?php

namespace Nawasara\Zoom\Jobs;

use Nawasara\Sync\Jobs\AbstractSyncJob;
use Nawasara\Zoom\Models\ZoomUser;
use Nawasara\Zoom\Services\ZoomClient;

/**
 * Pull the Zoom account's users into nawasara_zoom_users.
 *
 * Extends AbstractSyncJob so every run is tracked in nawasara_sync_jobs (the
 * "Sync Jobs" UI) like every other nawasara sync — previously this was a bare
 * ShouldQueue, which is why zoom never appeared there and auto-sync was
 * invisible. The actual work lives in execute(); the base class wraps it with
 * status tracking + retry/conflict handling.
 */
class SyncZoomUsersJob extends AbstractSyncJob
{
    public int $timeout = 300;

    protected function service(): string
    {
        return 'zoom';
    }

    protected function action(): string
    {
        return 'sync_users';
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

        // getUsers() now follows next_page_token internally, so this is the
        // full account, not just the first page.
        $remoteUsers = $client->getUsers()['users'] ?? [];

        $synced = 0;
        foreach ($remoteUsers as $remote) {
            $this->syncUser($remote);
            $synced++;
        }

        return ['synced' => $synced];
    }

    protected function syncUser(array $remote): void
    {
        ZoomUser::updateOrCreate(
            ['user_id' => $remote['id']],
            [
                'user_id' => $remote['id'],
                'email' => $remote['email'],
                'first_name' => $remote['first_name'] ?? '',
                'last_name' => $remote['last_name'] ?? '',
                'user_type' => $remote['type'],
                'license_type' => $remote['plan_united_type'] ?? null,
                'status' => $remote['status'] ?? 'active',
                'last_login_at' => $remote['last_login_time'] ?? null,
                'zoom_created_at' => $remote['created_at'] ?? null,
                'sync_status' => 'synced',
                'sync_error' => null,
                'last_synced_at' => now(),
            ]
        );
    }
}
