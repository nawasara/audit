<div>
    <x-slot name="breadcrumb">
        <livewire:nawasara-ui.shared-components.breadcrumb
            :items="[['label' => 'Audit', 'url' => '#'], ['label' => 'Impersonation Log']]" />
    </x-slot>

    {{-- Page title moved into Section\Table supaya bisa share row dengan
         time-window selector yang stateful — pattern same dengan
         LoginHistory. Index cuma host breadcrumb + container. --}}
    <x-nawasara-ui::page.container>
        @livewire('nawasara-audit.impersonation-log.section.table')
    </x-nawasara-ui::page.container>
</div>
