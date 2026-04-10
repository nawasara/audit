<div>
    <x-slot name="breadcrumb">
        <livewire:nawasara-ui.shared-components.breadcrumb
            :items="[['label' => 'Audit', 'url' => '#'], ['label' => 'Login History']]" />
    </x-slot>

    <x-nawasara-ui::page.container>
        <x-nawasara-ui::page.title>Login History</x-nawasara-ui::page.title>

        @livewire('nawasara-audit.login-history.section.table')
    </x-nawasara-ui::page.container>
</div>
