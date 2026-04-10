<?php

namespace Nawasara\Audit\Livewire\LoginHistory\Section;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Nawasara\Audit\Models\LoginAttempt;

class Table extends Component
{
    use WithPagination;

    public string $search = '';
    public string $statusFilter = '';
    public string $methodFilter = '';

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
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->methodFilter, fn ($q) => $q->where('method', $this->methodFilter))
            ->latest('created_at')
            ->paginate(15);
    }

    public function render()
    {
        return view('nawasara-audit::livewire.pages.login-history.section.table');
    }
}
