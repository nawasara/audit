<?php

namespace Nawasara\Audit\Livewire\ActivityLog;

use Livewire\Component;

class Index extends Component
{
    public function render()
    {
        return view('nawasara-audit::livewire.pages.activity-log.index')
            ->layout('nawasara-ui::components.layouts.app');
    }
}
