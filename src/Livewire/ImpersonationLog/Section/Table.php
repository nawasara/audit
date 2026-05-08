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
        $hasTeleport = Schema::hasTable('nawasara_teleport_sessions');

        // Type filter — kalau user pilih jenis tertentu, skip query lain
        // entirely. Performance optimization untuk large dataset.
        $wantWebmail = $hasWebmail && (empty($this->typeFilter) || in_array('webmail', $this->typeFilter, true));
        $wantCpanel = $hasCpanel && (empty($this->typeFilter) || in_array('cpanel', $this->typeFilter, true));
        $wantTeleport = $hasTeleport && (empty($this->typeFilter) || in_array('teleport', $this->typeFilter, true));

        $sources = [];

        if ($wantWebmail) {
            $sources[] = DB::table('nawasara_webmail_sessions')
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
            $sources[] = DB::table('nawasara_cpanel_sessions')
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

        if ($wantTeleport) {
            $sources[] = DB::table('nawasara_teleport_sessions')
                ->select(
                    DB::raw("'teleport' as type"),
                    'id',
                    'acted_by_user_id',
                    // target di Teleport = combo user@node (mis. "alice@Server-Wazuh")
                    // Lebih informatif di tabel daripada cuma node atau cuma user.
                    DB::raw("CONCAT(target_user, '@', node) as target"),
                    DB::raw('node as instance'),
                    'reason',
                    'status',
                    'ip',
                    'user_agent',
                    'created_at',
                );
        }

        // Resolve final query: handle 0/1/2/3 source kombinasi via UNION ALL
        // berturut-turut. Wrap di subquery supaya outer base bisa apply
        // common filters (time window, search, status) sekali saja.
        if (empty($sources)) {
            // No source available — return query yang akan match nothing.
            // Page tetap loadable dengan empty state.
            $base = DB::table(DB::raw("(SELECT NULL as type, NULL as id, NULL as acted_by_user_id, NULL as target, NULL as instance, NULL as reason, NULL as status, NULL as ip, NULL as user_agent, NULL as created_at WHERE 1=0) as sessions"));
        } elseif (count($sources) === 1) {
            $q = $sources[0];
            $base = DB::table(DB::raw("({$q->toSql()}) as sessions"))
                ->mergeBindings($q);
        } else {
            // 2+ sources — concat dengan UNION ALL.
            $sqlParts = array_map(fn ($q) => $q->toSql(), $sources);
            $unionSql = implode(' UNION ALL ', $sqlParts);
            $base = DB::table(DB::raw("({$unionSql}) as sessions"));
            foreach ($sources as $q) {
                $base->mergeBindings($q);
            }
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

    /**
     * Summary aggregates untuk hero stats di top page. Dihitung dari query
     * yang APPLY time window + type filter + actor filter + search, tapi
     * STRIP status filter — supaya cards "Issued/Failed" tetap reflect
     * total absolut di context aktif, bukan ke-double-filter dengan
     * status-yang-sedang-aktif.
     *
     * Pattern serupa dengan zone-health page: stat cards = clickable filter
     * untuk status. User klik "Failed" → set statusFilter=['failed'] → table
     * narrow ke failed only, tapi cards tetap show total + per-status count.
     */
    #[Computed]
    public function summary(): array
    {
        // Build query SAMA dengan baseQuery() tapi tanpa statusFilter.
        // Approach: temporary stash + restore $this->statusFilter, atau
        // panggil baseQuery + cabut where status di runtime. Cara cleanest
        // adalah duplicate logic — sedikit duplication acceptable untuk
        // single-purpose method.
        $stashedStatus = $this->statusFilter;
        $this->statusFilter = [];
        try {
            $q = $this->baseQuery();
            $total = (clone $q)->count();
            $issued = (clone $q)->where('status', 'issued')->count();
            $failed = (clone $q)->where('status', 'failed')->count();
            $rejected = (clone $q)->where('status', 'rejected')->count();
        } finally {
            $this->statusFilter = $stashedStatus;
        }

        return [
            'total' => $total,
            'issued' => $issued,
            'failed' => $failed,
            'rejected' => $rejected,
        ];
    }

    /**
     * Toggle status filter dari stat card click. Kalau sudah aktif (1
     * value match), klik lagi reset. Kalau belum aktif, replace filter
     * dengan exclusive single-status.
     */
    public function toggleStatusFilter(string $status): void
    {
        if ($this->statusFilter === [$status]) {
            $this->statusFilter = [];
        } else {
            $this->statusFilter = [$status];
        }
        $this->resetPage();
    }

    public function clearStatusFilter(): void
    {
        $this->statusFilter = [];
        $this->resetPage();
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
     * 3 tabel (webmail/cpanel/teleport), supaya filter cuma show user
     * yang relevan.
     */
    #[Computed]
    public function actorOptions(): array
    {
        $hasWebmail = Schema::hasTable('nawasara_webmail_sessions');
        $hasCpanel = Schema::hasTable('nawasara_cpanel_sessions');
        $hasTeleport = Schema::hasTable('nawasara_teleport_sessions');

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
        if ($hasTeleport) {
            $ids = $ids->merge(
                DB::table('nawasara_teleport_sessions')
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
            'teleport' => 'nawasara_teleport_sessions',
            default => null,
        };

        if (! $table || ! Schema::hasTable($table)) return null;

        $row = DB::table($table)->where('id', $this->detailKey)->first();
        if (! $row) return null;

        // Normalize ke shape yang sama dengan UNION rows untuk view consistency
        $row->type = $this->detailType;
        $row->target = match ($this->detailType) {
            'webmail' => $row->email_account ?? '-',
            'cpanel' => $row->cpanel_user ?? '-',
            'teleport' => ($row->target_user ?? '?').'@'.($row->node ?? '?'),
            default => '-',
        };
        $row->actor = $row->acted_by_user_id ? User::find($row->acted_by_user_id) : null;

        // Webmail row mungkin punya target_user_id (mailbox owner)
        if ($this->detailType === 'webmail' && ($row->user_id ?? null)) {
            $row->target_user = User::find($row->user_id);
        }

        // Teleport row punya extra fields untuk display: node, login,
        // ticket_id, duration_seconds. Sudah ada di $row from select *,
        // tinggal view yang baca.

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
