<?php

namespace Nawasara\Audit\Livewire\ImpersonationLog\Section;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Nawasara\Ui\Livewire\Concerns\HasArrayFilters;
use Nawasara\Ui\Livewire\Concerns\HasExport;
use Nawasara\Ui\Livewire\Concerns\HasTimeWindow;

/**
 * Audit log untuk admin impersonation events:
 *   - Webmail launch-as (admin masuk Roundcube as user X)
 *   - cPanel launch-as  (admin masuk cPanel as account Y)
 *
 * Read dari DUA tabel terpisah:
 *   - nawasara_webmail_sessions (owned by nawasara-whm)
 *   - nawasara_cpanel_sessions  (owned by nawasara-whm)
 *
 * Strategy: SQL UNION via DB::table dengan kolom common dinormalisasi:
 *   type, id, acted_by_user_id, target, reason, status, ip, user_agent, created_at
 *
 * Webmail-specific: untuk row launch_kind='self' (user buka emailnya sendiri,
 * bukan impersonation), kita SKIP karena bukan audit aktivitas admin. Filter
 * di-apply WHERE launch_kind='impersonation' di branch webmail.
 *
 * Defensive: kalau tabel sumber belum ada (mis. nawasara-whm belum di-install
 * atau migrate belum jalan), branch yang tidak punya tabel di-skip — page
 * tetap loadable, cuma data dari source itu absen.
 */
class Table extends Component
{
    use HasArrayFilters;
    use HasExport;
    use HasTimeWindow;
    use WithPagination;

    #[Url]
    public string $search = '';

    /**
     * Type filter as multi-select array (['webmail', 'cpanel']).
     * Empty array == semua type.
     *
     * @var array<int, string>
     */
    #[Url]
    public $typeFilter = [];

    /**
     * Status filter as multi-select array (['issued', 'failed', 'rejected']).
     * Empty array == semua status.
     *
     * @var array<int, string>
     */
    #[Url]
    public $statusFilter = [];

    /**
     * Filter by admin (acted_by_user_id) — single user dropdown. Empty
     * string == semua admin.
     */
    #[Url]
    public string $actorFilter = '';

    public int $perPage = 25;

    public ?int $detailKey = null;
    public ?string $detailType = null;

    /**
     * Filter properties yang accept scalar dari URL legacy bookmarks.
     */
    protected function arrayFilters(): array
    {
        return ['typeFilter', 'statusFilter'];
    }

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedTypeFilter(): void { $this->resetPage(); }
    public function updatedStatusFilter(): void { $this->resetPage(); }
    public function updatedActorFilter(): void { $this->resetPage(); }

    /**
     * Build base UNION query gabungan webmail + cpanel sessions. Skip
     * source kalau tabelnya belum exist (defensive: nawasara-whm belum
     * installed/migrated). Apply filter common di luar union supaya
     * tidak duplicate logic.
     */
    protected function baseQuery(): \Illuminate\Database\Query\Builder
    {
        $hasWebmail = Schema::hasTable('nawasara_webmail_sessions');
        $hasCpanel = Schema::hasTable('nawasara_cpanel_sessions');

        // Type filter — kalau user pilih cuma satu jenis, skip query lain
        // entirely. Performance optimization untuk large dataset.
        $wantWebmail = $hasWebmail && (empty($this->typeFilter) || in_array('webmail', $this->typeFilter, true));
        $wantCpanel = $hasCpanel && (empty($this->typeFilter) || in_array('cpanel', $this->typeFilter, true));

        $webmailQ = null;
        $cpanelQ = null;

        if ($wantWebmail) {
            $webmailQ = DB::table('nawasara_webmail_sessions')
                ->select(
                    DB::raw("'webmail' as type"),
                    'id',
                    'acted_by_user_id',
                    DB::raw('email_account as target'),
                    DB::raw('NULL as instance'),
                    'reason',
                    'status',
                    'ip',
                    'user_agent',
                    'created_at',
                )
                // Hanya impersonation row — self-launch (user buka email-nya
                // sendiri lewat /webmail/launch) bukan domain audit page ini.
                ->where('launch_kind', 'impersonation');
        }

        if ($wantCpanel) {
            $cpanelQ = DB::table('nawasara_cpanel_sessions')
                ->select(
                    DB::raw("'cpanel' as type"),
                    'id',
                    'acted_by_user_id',
                    DB::raw('cpanel_user as target'),
                    'instance',
                    'reason',
                    'status',
                    'ip',
                    'user_agent',
                    'created_at',
                );
        }

        // Resolve final query: union kalau dua-duanya ada, single kalau
        // satu, atau dummy empty kalau dua-duanya skip (mis. fresh install
        // tanpa whm package).
        if ($webmailQ && $cpanelQ) {
            $base = DB::table(
                DB::raw("({$webmailQ->toSql()} UNION ALL {$cpanelQ->toSql()}) as sessions")
            )
                ->mergeBindings($webmailQ)
                ->mergeBindings($cpanelQ);
        } elseif ($webmailQ) {
            $base = DB::table(DB::raw("({$webmailQ->toSql()}) as sessions"))
                ->mergeBindings($webmailQ);
        } elseif ($cpanelQ) {
            $base = DB::table(DB::raw("({$cpanelQ->toSql()}) as sessions"))
                ->mergeBindings($cpanelQ);
        } else {
            // No source available — return query yang akan match nothing.
            // Pakai unioned of selected zero rows lebih aman dari throw
            // exception (page tetap render dengan empty state).
            $base = DB::table(DB::raw("(SELECT NULL as type, NULL as id, NULL as acted_by_user_id, NULL as target, NULL as instance, NULL as reason, NULL as status, NULL as ip, NULL as user_agent, NULL as created_at WHERE 1=0) as sessions"));
        }

        // Common filters di luar union — applied to wrapper query.
        $base->tap(fn ($q) => $this->applyTimeWindow($q, 'created_at'));

        if (! empty($this->statusFilter)) {
            $base->whereIn('status', $this->statusFilter);
        }

        if ($this->actorFilter !== '') {
            $base->where('acted_by_user_id', (int) $this->actorFilter);
        }

        if ($this->search !== '') {
            $needle = '%'.$this->search.'%';
            $base->where(function ($q) use ($needle) {
                $q->where('target', 'like', $needle)
                    ->orWhere('reason', 'like', $needle)
                    ->orWhere('ip', 'like', $needle);
            });
        }

        return $base;
    }

    #[Computed]
    public function items()
    {
        $page = $this->baseQuery()
            ->orderBy('created_at', 'desc')
            ->paginate($this->perPage);

        // Eager-load actor users (acted_by_user_id) supaya tidak N+1 di view.
        // UNION query return generic stdClass rows, jadi join manual via
        // collection enrichment.
        $actorIds = collect($page->items())
            ->pluck('acted_by_user_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $actors = empty($actorIds)
            ? collect()
            : User::whereIn('id', $actorIds)->get()->keyBy('id');

        $page->getCollection()->transform(function ($row) use ($actors) {
            $row->actor = $actors->get($row->acted_by_user_id);
            return $row;
        });

        return $page;
    }

    /**
     * List admin (user) yang pernah lakukan impersonation, untuk dropdown
     * filter "By Admin". Pre-resolve dari distinct acted_by_user_id di
     * dua tabel, supaya filter cuma show user yang relevan.
     */
    #[Computed]
    public function actorOptions(): array
    {
        $hasWebmail = Schema::hasTable('nawasara_webmail_sessions');
        $hasCpanel = Schema::hasTable('nawasara_cpanel_sessions');

        $ids = collect();

        if ($hasWebmail) {
            $ids = $ids->merge(
                DB::table('nawasara_webmail_sessions')
                    ->where('launch_kind', 'impersonation')
                    ->whereNotNull('acted_by_user_id')
                    ->distinct()
                    ->pluck('acted_by_user_id'),
            );
        }
        if ($hasCpanel) {
            $ids = $ids->merge(
                DB::table('nawasara_cpanel_sessions')
                    ->whereNotNull('acted_by_user_id')
                    ->distinct()
                    ->pluck('acted_by_user_id'),
            );
        }

        $ids = $ids->unique()->values();
        if ($ids->isEmpty()) return [];

        return User::whereIn('id', $ids)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * Single row detail untuk modal — di-resolve dari $detailType +
     * $detailKey supaya tidak perlu serialize whole row di state.
     */
    #[Computed]
    public function detail(): ?\stdClass
    {
        if (! $this->detailType || ! $this->detailKey) {
            return null;
        }

        $table = match ($this->detailType) {
            'webmail' => 'nawasara_webmail_sessions',
            'cpanel' => 'nawasara_cpanel_sessions',
            default => null,
        };

        if (! $table || ! Schema::hasTable($table)) return null;

        $row = DB::table($table)->where('id', $this->detailKey)->first();
        if (! $row) return null;

        // Normalize ke shape yang sama dengan UNION rows untuk view consistency
        $row->type = $this->detailType;
        $row->target = $this->detailType === 'webmail'
            ? ($row->email_account ?? '-')
            : ($row->cpanel_user ?? '-');
        $row->actor = $row->acted_by_user_id ? User::find($row->acted_by_user_id) : null;

        // For webmail row, resolve target user (kalau email ke-link ke user)
        if ($this->detailType === 'webmail' && ($row->user_id ?? null)) {
            $row->target_user = User::find($row->user_id);
        }

        return $row;
    }

    public function openDetail(string $type, int $id): void
    {
        $this->detailType = $type;
        $this->detailKey = $id;
        $this->dispatch('modal-open:impersonation-detail');
    }

    public function closeDetail(): void
    {
        $this->dispatch('modal-close:impersonation-detail');
        $this->detailType = null;
        $this->detailKey = null;
    }

    /**
     * Export filename base — timestamp + extension appended by HasExport.
     */
    protected function exportFilename(): string
    {
        return 'impersonation-log';
    }

    /**
     * Export FULL filtered dataset (capped 10k baris) sesuai filter aktif
     * + time window. Audit reviewer butuh raw context (IP, UA, reason).
     */
    protected function exportData(): iterable
    {
        $rows = $this->baseQuery()
            ->orderBy('created_at', 'desc')
            ->limit(10000)
            ->get();

        $actorIds = $rows->pluck('acted_by_user_id')->filter()->unique()->all();
        $actors = empty($actorIds) ? collect() : User::whereIn('id', $actorIds)->get()->keyBy('id');

        return $rows->map(fn ($r) => [
            'ID' => $r->id,
            'Type' => $r->type,
            'Waktu' => $r->created_at,
            'Admin (Actor)' => optional($actors->get($r->acted_by_user_id))->name ?? '-',
            'Target' => $r->target,
            'Instance' => $r->instance ?? '-',
            'Status' => $r->status,
            'Alasan' => $r->reason ?? '-',
            'IP' => $r->ip ?? '-',
            'User Agent' => $r->user_agent ?? '-',
        ]);
    }

    public function render()
    {
        return view('nawasara-audit::livewire.pages.impersonation-log.section.table');
    }
}
