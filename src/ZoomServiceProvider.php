<?php

namespace Nawasara\Zoom;

use Livewire\Livewire;
use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;
use Illuminate\Support\ServiceProvider;
use Nawasara\Zoom\Console\Commands\HealthCheckCommand;
use Nawasara\Zoom\Console\Commands\SyncCommand;
use Nawasara\Zoom\Services\ZoomClient;

class ZoomServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'nawasara-zoom');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->registerLivewire();

        if ($this->app->runningInConsole()) {
            $this->commands([
                HealthCheckCommand::class,
                SyncCommand::class,
            ]);
        }
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
