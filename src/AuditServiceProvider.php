<?php

namespace Nawasara\Audit;

use Livewire\Livewire;
use Illuminate\Support\Str;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Failed;
use Symfony\Component\Finder\Finder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
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
