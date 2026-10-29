<?php

namespace Nawasara\Audit;

use Livewire\Livewire;
use Illuminate\Support\Str;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Failed;
use Symfony\Component\Finder\Finder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Nawasara\Audit\Console\Commands\PruneAuditLogsCommand;
use Nawasara\Audit\Listeners\LogFailedLogin;
use Nawasara\Audit\Listeners\LogSuccessfulLogin;

class AuditServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'nawasara-audit');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->registerLivewire();
        $this->registerEventListeners();
        $this->offerPublishing();
        $this->registerPrune();
    }

    /**
     * The prune command, plus its nightly schedule when enabled.
     *
     * Scheduled with $schedule->call() rather than ->command(): commands
     * registered by a package do not reliably reach the Artisan kernel when
     * the scheduler boots (AGENTS.md §7).
     */
    protected function registerPrune(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([PruneAuditLogsCommand::class]);

        $this->app->booted(function () {
            if (! config('nawasara-audit.prune.enabled', false)) {
                return;
            }

            $this->app->make(Schedule::class)
                ->call(fn () => Artisan::call('nawasara-audit:prune'))
                ->name('nawasara-audit:prune')
                ->dailyAt((string) config('nawasara-audit.prune.at', '02:30'))
                ->timezone('Asia/Jakarta')
                ->withoutOverlapping(60);
        });
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/nawasara-audit.php', 'nawasara-audit');
    }

    protected function registerEventListeners(): void
    {
        if (! config('nawasara-audit.enabled', true)) {
            return;
        }

        Event::listen(Login::class, LogSuccessfulLogin::class);
        Event::listen(Failed::class, LogFailedLogin::class);
    }

    public function registerLivewire(): void
    {
        $namespace = 'Nawasara\\Audit\\Livewire';
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
                $alias = 'nawasara-audit.'.
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

    protected function offerPublishing(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/nawasara-audit.php' => config_path('nawasara-audit.php'),
        ], 'nawasara-audit:config');
    }
}
