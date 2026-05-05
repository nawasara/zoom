<?php

namespace Nawasara\Zoom\Console\Commands;

use Illuminate\Console\Command;
use Nawasara\Zoom\Jobs\SyncZoomUsersJob;
use Nawasara\Zoom\Jobs\SyncZoomMeetingsJob;
use Nawasara\Zoom\Jobs\SyncZoomRecordingsJob;

class SyncCommand extends Command
{
    protected $signature = 'zoom:sync {type? : users|meetings|recordings|all}';

    protected $description = 'Manually trigger Zoom sync jobs';

    public function handle()
    {
        $type = $this->argument('type') ?? 'all';

        $this->info("Starting Zoom sync for: {$type}");

        match ($type) {
            'users' => SyncZoomUsersJob::dispatch(),
            'meetings' => SyncZoomMeetingsJob::dispatch(),
            'recordings' => SyncZoomRecordingsJob::dispatch(),
            'all' => [
                SyncZoomUsersJob::dispatch(),
                SyncZoomMeetingsJob::dispatch(),
                SyncZoomRecordingsJob::dispatch(),
            ],
            default => $this->error("Invalid type: {$type}"),
        };

        $this->info('Sync jobs queued successfully');
    }
}
