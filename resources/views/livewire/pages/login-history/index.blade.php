<div>
    <x-slot name="breadcrumb">
        <livewire:nawasara-ui.shared-components.breadcrumb
            :items="[['label' => 'Audit', 'url' => '#'], ['label' => 'Login History']]" />
    </x-slot>

    {{-- Page title moved into the Livewire section table so it can sit
         alongside the time-window selector (which lives inside the
         component's state). Index just hosts breadcrumb + section. --}}
    <x-nawasara-ui::page.container>
        @livewire('nawasara-audit.login-history.section.table')
    </x-nawasara-ui::page.container>
</div>
