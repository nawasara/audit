# Nawasara Audit

Audit trail, activity logging, and login attempt tracking for the Nawasara superapp framework.

## Features

- **Activity log.** Every significant write action (model create/update/delete) is recorded with the user, timestamp, and field-level diff using `spatie/laravel-activitylog`.
- **Login history.** Successful and failed authentication attempts are persisted, including IP address, user agent, and outcome.
- **Admin viewer.** Livewire pages to browse, filter, and inspect activity log entries and login attempts without leaving Nawasara.
- **Permission-gated.** Viewing the audit pages requires Spatie permissions seeded by the package.

## Installation

This package ships as part of the Nawasara monorepo and is auto-discovered by Laravel.

```bash
composer require nawasara/audit
php artisan migrate
php artisan db:seed --class="Nawasara\Audit\Database\Seeders\PermissionSeeder" --force
```

## Usage

Enable activity logging on any Eloquent model:

```php
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Post extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'body'])
            ->logOnlyDirty();
    }
}
```

Login attempts are recorded automatically by the bundled listeners; no caller code is required.

## Pages

| Route | Permission | Purpose |
|-------|-----------|---------|
| `/admin/audit/activity` | `audit.activity.view` | Filterable activity log viewer |
| `/admin/audit/login-history` | `audit.login.view` | Login attempt history |

## Retention and pruning

`nawasara-audit:prune` deletes rows older than `NAWASARA_AUDIT_RETENTION_DAYS` (default 90) from the shared `activity_log` and from `nawasara_audit_login_attempts`, in batches.

```bash
php artisan nawasara-audit:prune --dry-run   # count only
php artisan nawasara-audit:prune             # delete
```

| Variable | Default | Meaning |
|---|---|---|
| `NAWASARA_AUDIT_RETENTION_DAYS` | `90` | Days to keep. Below 30 or not a whole number: the prune refuses to run |
| `NAWASARA_AUDIT_PRUNE_ENABLED` | `false` | Schedule the prune nightly |
| `NAWASARA_AUDIT_PRUNE_AT` | `02:30` | Time of day, Asia/Jakarta |

Decisions not to undo:

- **The nightly prune is off by default.** `activity_log` holds the audit trail of every package. The first run on an environment that has never been pruned deletes all older history at once and cannot be undone, so it has to be a deliberate choice: run `--dry-run`, decide whether 90 days is long enough for every package's audit needs, then turn it on.
- **A bad retention value stops the prune instead of widening it.** `0`, `9` typed for `90`, or an empty string would otherwise delete nearly everything. The minimum is 30 days (`Support\RetentionPolicy`).
- **Until October 2026 nothing read `retention_days`.** The setting existed and the docs said 90 days while both tables grew without limit.

## Author

**Pringgo J. Saputro** &lt;odyinggo@gmail.com&gt;

## License

MIT
