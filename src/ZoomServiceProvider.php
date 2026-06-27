<?php

namespace Nawasara\Zoom;

use Livewire\Livewire;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;
use Illuminate\Support\ServiceProvider;
use Nawasara\Zoom\Console\Commands\HealthCheckCommand;
use Nawasara\Zoom\Console\Commands\SyncCommand;
use Nawasara\Zoom\Jobs\SyncZoomMeetingsJob;
use Nawasara\Zoom\Jobs\SyncZoomRecordingsJob;
use Nawasara\Zoom\Jobs\SyncZoomUsersJob;
use Nawasara\Zoom\Services\ZoomClient;

class ZoomServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'nawasara-zoom');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->registerLivewire();
        $this->registerSchedule();

        if ($this->app->runningInConsole()) {
            $this->commands([
                HealthCheckCommand::class,
                SyncCommand::class,
            ]);
        }
    }

    /**
     * Auto-sync schedule. Uses $schedule->call() (NOT ->command()) because
     * package console commands don't reliably surface in the Artisan kernel
     * when the scheduler process boots — see reference_schedule_call_workaround.
     * Without this method the sync jobs were never dispatched, which is why
     * Zoom showed 0 users in production.
     */
    protected function registerSchedule(): void
    {
        $this->app->booted(function () {
            if (! $this->app->runningInConsole()) {
                return;
            }
            if (! config('nawasara-zoom.scheduler.enabled', true)) {
                return;
            }

            $schedule = $this->app->make(Schedule::class);

            $userInterval = max(1, (int) config('nawasara-zoom.user_sync_interval', 60));
            $meetingInterval = max(1, (int) config('nawasara-zoom.meeting_sync_interval', 5));
            $recordingInterval = max(1, (int) config('nawasara-zoom.recording_sync_interval', 30));

            $schedule->call(fn () => SyncZoomUsersJob::dispatch(triggerSource: 'scheduled'))
                ->name('nawasara-zoom:sync-users')
                ->cron("*/{$userInterval} * * * *")
                ->withoutOverlapping(10);

            $schedule->call(fn () => SyncZoomMeetingsJob::dispatch(triggerSource: 'scheduled'))
                ->name('nawasara-zoom:sync-meetings')
                ->cron("*/{$meetingInterval} * * * *")
                ->withoutOverlapping(10);

            $schedule->call(fn () => SyncZoomRecordingsJob::dispatch(triggerSource: 'scheduled'))
                ->name('nawasara-zoom:sync-recordings')
                ->cron("*/{$recordingInterval} * * * *")
                ->withoutOverlapping(10);

            // History backfill — slow + occasional. Daily off-peak captures
            // past/instant meetings + recordings the live sync misses.
            $schedule->call(fn () => SyncZoomMeetingsJob::dispatch(payload: ['history' => true], triggerSource: 'scheduled'))
                ->name('nawasara-zoom:sync-meetings-history')
                ->dailyAt('02:30')
                ->withoutOverlapping(30);

            $schedule->call(fn () => SyncZoomRecordingsJob::dispatch(payload: ['history' => true], triggerSource: 'scheduled'))
                ->name('nawasara-zoom:sync-recordings-history')
                ->dailyAt('03:00')
                ->withoutOverlapping(30);
        });
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/nawasara-zoom.php', 'nawasara-zoom');

        $this->app->singleton(ZoomClient::class, fn () => new ZoomClient());
    }

    public function registerLivewire(): void
    {
        $namespace = 'Nawasara\\Zoom\\Livewire';
        $basePath = __DIR__.'/Livewire';

        if (! is_dir($basePath)) {
            return;
        }

        $finder = new Finder();
        $finder->files()->in($basePath)->name('*.php');

        foreach ($finder as $file) {
            $relativePath = str_replace('/', '\\', $file->getRelativePathname());
            $class = $namespace.'\\'.Str::beforeLast($relativePath, '.php');

            if (class_exists($class)) {
                $alias = 'nawasara-zoom.'.
                    Str::of($relativePath)
                        ->replace('.php', '')
                        ->replace('\\', '.')
                        ->replace('/', '.')
                        ->explode('.')
                        ->map(fn ($segment) => Str::kebab($segment))
                        ->join('.');

                Livewire::component($alias, $class);
            }
        }
    }
}
