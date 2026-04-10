<div>
    <x-slot name="breadcrumb">
        <livewire:nawasara-ui.shared-components.breadcrumb
            :items="[['label' => 'Audit', 'url' => '#'], ['label' => 'Activity Log']]" />
    </x-slot>

    <x-nawasara-ui::page.container>
        <x-nawasara-ui::page.title>Activity Log</x-nawasara-ui::page.title>

        @livewire('nawasara-audit.activity-log.section.table')
    </x-nawasara-ui::page.container>
</div>
