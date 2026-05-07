<?php

namespace Nawasara\Audit\Livewire\LoginHistory\Section;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Nawasara\Audit\Models\LoginAttempt;
use Nawasara\Ui\Livewire\Concerns\HasExport;

class Table extends Component
{
    use HasExport;
    use WithPagination;

    public string $search = '';

    /**
     * Status filter as multi-select array (['success', 'failed']).
     * Empty array == no filter.
     *
     * @var array<int, string>
     */
    public array $statusFilter = [];

    /**
     * Login-method filter as multi-select array (['local', 'sso']).
     * Empty array == no filter.
     *
     * @var array<int, string>
     */
    public array $methodFilter = [];

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedStatusFilter()
    {
        $this->resetPage();
    }

    public function updatedMethodFilter()
    {
        $this->resetPage();
    }

    #[Computed]
    public function items()
    {
        return LoginAttempt::query()
            ->with('user')
            ->search($this->search)
            ->when(! empty($this->statusFilter), fn ($q) => $q->whereIn('status', $this->statusFilter))
            ->when(! empty($this->methodFilter), fn ($q) => $q->whereIn('method', $this->methodFilter))
            ->latest('created_at')
            ->paginate(15);
    }

    /**
     * Export filename base — timestamp + extension appended by HasExport.
     */
    protected function exportFilename(): string
    {
        return 'login-history';
    }

    /**
     * Export FULL login-attempt history (capped) per spec. User-agent strings
     * can be long; included verbatim because that's what an audit reviewer
     * would want to inspect offline.
     */
    protected function exportData(): iterable
    {
        return LoginAttempt::query()
            ->with('user')
            ->latest('created_at')
            ->limit(10000)
            ->get()
            ->map(fn (LoginAttempt $a) => [
                'ID' => $a->id,
                'Waktu' => optional($a->created_at)->format('Y-m-d H:i:s'),
                'User' => $a->user?->name,
                'Username Attempted' => $a->username_attempted,
                'Status' => $a->status,
                'Metode' => $a->method,
                'IP Address' => $a->ip_address,
                'User Agent' => $a->user_agent,
            ]);
    }

    public function render()
    {
        return view('nawasara-audit::livewire.pages.login-history.section.table');
    }
}
