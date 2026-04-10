<?php

use Illuminate\Support\Facades\Route;
use Nawasara\Audit\Livewire\ActivityLog\Index as ActivityLogIndex;
use Nawasara\Audit\Livewire\LoginHistory\Index as LoginHistoryIndex;

Route::middleware(['web', 'auth'])->prefix('nawasara-audit')->group(function () {
    Route::get('activity-log', ActivityLogIndex::class)->name('nawasara-audit.activity-log.index');
    Route::get('login-history', LoginHistoryIndex::class)->name('nawasara-audit.login-history.index');
});
