<?php

namespace Nawasara\Audit\Livewire\ImpersonationLog;

use Livewire\Component;

/**
 * Page-level container untuk Impersonation Log audit. Sengaja minimal
 * (mirror pattern LoginHistory\Index) — semua state + filter + table
 * di Section\Table component supaya page reload-free saat filter ganti.
 */
class Index extends Component
{
    public function render()
    {
        return view('nawasara-audit::livewire.pages.impersonation-log.index')
            ->layout('nawasara-ui::components.layouts.app');
    }
}
