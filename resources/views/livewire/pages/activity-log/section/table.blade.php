<div>
    {{-- Filters --}}
    <div class="mb-4 flex flex-col sm:flex-row gap-3">
        <div class="flex-1">
            <x-nawasara-ui::form.input type="text" placeholder="Cari user, model, deskripsi..."
                wire:model.live.debounce.300ms="search" />
        </div>
        <div class="w-full sm:w-48">
            <x-nawasara-ui::form.select wire:model.live="eventFilter">
                <option value="">Semua Aksi</option>
                <option value="created">Created</option>
                <option value="updated">Updated</option>
                <option value="deleted">Deleted</option>
            </x-nawasara-ui::form.select>
        </div>
    </div>

    <x-nawasara-ui::table :headers="['#', 'User', 'Model', 'Aksi', 'Deskripsi', 'Tanggal']" title="Activity Log">
        <x-slot:table>
            @forelse ($this->items as $item)
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-neutral-400">
                        {{ $item->id }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 dark:text-neutral-200">
                        {{ $item->causer?->name ?? 'System' }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 dark:text-neutral-200">
                        {{ class_basename($item->subject_type ?? '-') }}
                        @if($item->subject_id)
                            <span class="text-gray-400">#{{ $item->subject_id }}</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        @php
                            $badgeClass = match($item->event) {
                                'created' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
                                'updated' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
                                'deleted' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
                                default => 'bg-gray-100 text-gray-800 dark:bg-neutral-700 dark:text-neutral-300',
                            };
                        @endphp
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $badgeClass }}">
                            {{ ucfirst($item->event ?? 'unknown') }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-800 dark:text-neutral-200 max-w-xs truncate">
                        {{ $item->description ?? '-' }}
                        @if($item->properties && $item->properties->has('old'))
                            <button type="button"
                                x-data
                                x-on:click="$dispatch('open-detail', { id: {{ $item->id }} })"
                                class="ml-1 text-blue-600 hover:underline text-xs">
                                detail
                            </button>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 dark:text-neutral-200">
                        {{ $item->created_at->format('d M Y H:i') }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-neutral-400">
                        Belum ada activity log.
                    </td>
                </tr>
            @endforelse
        </x-slot:table>

        <x-slot:footer>
            {{ $this->items->links() }}
        </x-slot:footer>
    </x-nawasara-ui::table>
</div>
