<div>
    {{-- Filters --}}
    <div class="mb-4 flex flex-col sm:flex-row gap-3">
        <div class="flex-1">
            <x-nawasara-ui::form.input type="text" placeholder="Cari username, IP address..."
                wire:model.live.debounce.300ms="search" />
        </div>
        <div class="w-full sm:w-40">
            <x-nawasara-ui::form.select wire:model.live="statusFilter">
                <option value="">Semua Status</option>
                <option value="success">Success</option>
                <option value="failed">Failed</option>
            </x-nawasara-ui::form.select>
        </div>
        <div class="w-full sm:w-40">
            <x-nawasara-ui::form.select wire:model.live="methodFilter">
                <option value="">Semua Metode</option>
                <option value="local">Local</option>
                <option value="sso">SSO</option>
            </x-nawasara-ui::form.select>
        </div>
    </div>

    <x-nawasara-ui::table :headers="['#', 'User', 'Username', 'Status', 'Metode', 'IP Address', 'User Agent', 'Waktu']" title="Login History">
        <x-slot:table>
            @forelse ($this->items as $item)
                <tr>
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
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300">
                                Success
                            </span>
                        @else
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300">
                                Failed
                            </span>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        @if ($item->method === 'sso')
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300">
                                SSO
                            </span>
                        @else
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-neutral-700 dark:text-neutral-300">
                                Local
                            </span>
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
                    <td colspan="8" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-neutral-400">
                        Belum ada riwayat login.
                    </td>
                </tr>
            @endforelse
        </x-slot:table>

        <x-slot:footer>
            {{ $this->items->links() }}
        </x-slot:footer>
    </x-nawasara-ui::table>
</div>
