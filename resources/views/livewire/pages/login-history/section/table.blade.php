<div>
    @php
        $statusOptions = ['success' => 'Success', 'failed' => 'Failed'];
        $methodOptions = ['local' => 'Local', 'sso' => 'SSO'];
    @endphp

    {{-- Toolbar — 2 filters (Status + Metode) + search + export. --}}
    <div class="space-y-2 mb-4">
        <div class="flex flex-col md:flex-row md:flex-nowrap md:items-center gap-2">
            <div class="flex flex-wrap items-center gap-2 shrink-0">
                <x-nawasara-ui::filter-panel
                    label="Filter"
                    :state="['statusFilter' => $statusFilter, 'methodFilter' => $methodFilter]"
                    :multiple="['statusFilter', 'methodFilter']"
                    :labels="['statusFilter' => $statusOptions, 'methodFilter' => $methodOptions]"
                    :dimensions="['statusFilter' => 'Status', 'methodFilter' => 'Metode']">
                    <x-nawasara-ui::filter-group label="Status" model="statusFilter" :items="$statusOptions" icon="lucide-shield-check" />
                    <x-nawasara-ui::filter-group label="Metode" model="methodFilter" :items="$methodOptions" icon="lucide-key-round" />
                </x-nawasara-ui::filter-panel>
            </div>

            <div class="relative w-full md:flex-1 md:min-w-0">
                <div class="absolute inset-y-0 start-0 flex items-center pointer-events-none ps-3.5">
                    <x-lucide-search class="shrink-0 size-4 text-gray-400 dark:text-neutral-500" />
                </div>
                <input type="text" wire:model.live.debounce.300ms="search"
                    placeholder="Cari username atau IP address..."
                    class="h-10 ps-10 pe-4 block w-full border border-gray-200 rounded-lg text-sm focus:border-emerald-600 focus:ring-emerald-600 dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-200 dark:placeholder-neutral-500 dark:focus:ring-neutral-600" />
            </div>

            <div class="flex items-center gap-2 shrink-0">
                <x-nawasara-ui::export-button
                    action="export"
                    tooltip="Ekspor login history (max 10rb baris)" />
            </div>
        </div>

        <div wire:ignore data-filter-chips></div>

        @if ($search)
            <div class="flex flex-wrap items-center gap-2">
                <x-nawasara-ui::filter-chip label="Cari: {{ $search }}" model="search" />
            </div>
        @endif
    </div>

    {{-- No stickyLast: read-only audit log, no action column. --}}
    <x-nawasara-ui::table
        :headers="['#', 'User', 'Username', 'Status', 'Metode', 'IP Address', 'User Agent', 'Waktu']"
        :title="'Login History ('.$this->items->total().' attempts)'">
        <x-slot:table>
            @forelse ($this->items as $item)
                <tr wire:key="login-{{ $item->id }}">
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-neutral-400">
                        {{ $item->id }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 dark:text-neutral-200">
                        {{ $item->user?->name ?? '-' }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 dark:text-neutral-200">
                        {{ $item->username_attempted }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        @if ($item->status === 'success')
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300">Success</span>
                        @else
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300">Failed</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        @if ($item->method === 'sso')
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-cyan-100 text-cyan-800 dark:bg-cyan-900/40 dark:text-cyan-300">SSO</span>
                        @else
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-neutral-700 dark:text-neutral-300">Local</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 dark:text-neutral-200">
                        {{ $item->ip_address ?? '-' }}
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-800 dark:text-neutral-200 max-w-xs truncate" title="{{ $item->user_agent }}">
                        {{ Str::limit($item->user_agent, 40) ?? '-' }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 dark:text-neutral-200">
                        {{ $item->created_at->format('d M Y H:i:s') }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8">
                        @if ($search || ! empty($statusFilter) || ! empty($methodFilter))
                            <x-nawasara-ui::empty-state
                                icon="lucide-search-x"
                                title="Tidak ada riwayat yang cocok"
                                description="Coba ubah filter atau hapus search keyword."
                                variant="filter"
                                inline />
                        @else
                            <x-nawasara-ui::empty-state
                                icon="lucide-log-in"
                                title="Belum ada riwayat login"
                                description="Setiap login user akan ter-record otomatis di sini."
                                inline />
                        @endif
                    </td>
                </tr>
            @endforelse
        </x-slot:table>

        <x-slot:footer>
            {{ $this->items->links() }}
        </x-slot:footer>
    </x-nawasara-ui::table>
</div>
