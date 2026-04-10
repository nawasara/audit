<?php

namespace Nawasara\Audit\Livewire\LoginHistory;

use Livewire\Component;

class Index extends Component
{
    public function render()
    {
        return view('nawasara-audit::livewire.pages.login-history.index')
            ->layout('nawasara-ui::components.layouts.app');
    }
}
