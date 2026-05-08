<?php

use Illuminate\Support\Facades\Route;
use Nawasara\Audit\Livewire\ActivityLog\Index as ActivityLogIndex;
use Nawasara\Audit\Livewire\ImpersonationLog\Index as ImpersonationLogIndex;
use Nawasara\Audit\Livewire\LoginHistory\Index as LoginHistoryIndex;
use Spatie\Permission\Middleware\PermissionMiddleware;

Route::middleware(['web', 'auth'])->prefix('nawasara-audit')->group(function () {
    Route::get('activity-log', ActivityLogIndex::class)->name('nawasara-audit.activity-log.index');
    Route::get('login-history', LoginHistoryIndex::class)->name('nawasara-audit.login-history.index');

    // Impersonation log — pakai permission terpisah dari general audit.log.view
    // karena content-nya sensitive (siapa admin akses email/cpanel siapa).
    // Kita sengaja ENFORCE permission di route level (bukan cuma sidebar
    // hide) supaya direct URL access juga ke-protect.
    Route::get('impersonation-log', ImpersonationLogIndex::class)
        ->middleware(PermissionMiddleware::using('audit.impersonation.view'))
        ->name('nawasara-audit.impersonation-log.index');
});
