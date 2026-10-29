<?php

namespace Nawasara\Audit\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Nawasara\Audit\Support\RetentionPolicy;

/**
 * Delete audit rows older than `nawasara-audit.retention_days`.
 *
 * Covers the shared Spatie `activity_log` (every package writes to it) and
 * `nawasara_audit_login_attempts`. Until this command existed the setting
 * was read by nothing, so both tables grew forever while the documentation
 * said 90 days.
 *
 * Deletes in batches: a single DELETE over millions of rows holds locks long
 * enough to stall every request that writes an activity entry meanwhile.
 *
 * Run with --dry-run first on any environment that has never been pruned:
 * the first real run removes everything older than the window at once, and
 * that is permanent.
 */
class PruneAuditLogsCommand extends Command
{
    protected $signature = 'nawasara-audit:prune
        {--dry-run : Count the rows that would be deleted, delete nothing}
        {--batch=5000 : Rows per DELETE}';

    protected $description = 'Delete activity log and login attempt rows older than the configured retention';

    public function handle(): int
    {
        $days = RetentionPolicy::days(config('nawasara-audit.retention_days'));

        if ($days === null) {
            $this->error('nawasara-audit.retention_days must be a whole number of at least '
                .RetentionPolicy::MINIMUM_DAYS.'. Nothing was deleted.');

            return self::FAILURE;
        }

        $cutoff = now()->subDays($days);
        $batch = max(100, (int) $this->option('batch'));
        $dryRun = (bool) $this->option('dry-run');

        $tables = [
            (string) config('activitylog.table_name', 'activity_log'),
            'nawasara_audit_login_attempts',
        ];

        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                $this->line("{$table}: table not found, skipped");

                continue;
            }

            $query = fn () => DB::table($table)->where('created_at', '<', $cutoff);

            if ($dryRun) {
                $this->line(sprintf('%s: %d rows older than %s would be deleted',
                    $table, $query()->count(), $cutoff->toDateString()));

                continue;
            }

            $deleted = 0;

            do {
                $ids = $query()->orderBy('id')->limit($batch)->pluck('id');

                if ($ids->isEmpty()) {
                    break;
                }

                $deleted += DB::table($table)->whereIn('id', $ids)->delete();
            } while ($ids->count() === $batch);

            $this->line(sprintf('%s: %d rows older than %s deleted', $table, $deleted, $cutoff->toDateString()));
        }

        return self::SUCCESS;
    }
}
