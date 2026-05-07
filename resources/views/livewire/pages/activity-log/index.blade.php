<div>
    <x-slot name="breadcrumb">
        <livewire:nawasara-ui.shared-components.breadcrumb
            :items="[['label' => 'Audit', 'url' => '#'], ['label' => 'Activity Log']]" />
    </x-slot>

    <x-nawasara-ui::page.container>
        {{-- Title moved into the section table component (alongside the
             time-window selector). Index just hosts breadcrumb + section. --}}
        @livewire('nawasara-audit.activity-log.section.table')
    </x-nawasara-ui::page.container>
</div>
