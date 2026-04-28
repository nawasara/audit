# Nawasara Audit

Audit trail, activity logging, and login attempt tracking for the Nawasara superapp framework.

## Features

- **Activity log** — every significant write action (model create/update/delete) is recorded with the user, timestamp, and field-level diff using `spatie/laravel-activitylog`.
- **Login history** — successful and failed authentication attempts are persisted, including IP address, user agent, and outcome.
- **Admin viewer** — Livewire pages to browse, filter, and inspect activity log entries and login attempts without leaving Nawasara.
- **Permission-gated** — viewing the audit pages requires Spatie permissions seeded by the package.

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

## Author

**Pringgo J. Saputro** &lt;odyinggo@gmail.com&gt;

## License

MIT
