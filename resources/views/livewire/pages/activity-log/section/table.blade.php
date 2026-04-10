<div>
    <x-nawasara-ui::filter-bar searchPlaceholder="Cari user, model, deskripsi..." searchModel="search">
        <x-nawasara-ui::filter-dropdown
            label="Aksi"
            model="eventFilter"
            :items="['all' => 'Semua Aksi', 'created' => 'Created', 'updated' => 'Updated', 'deleted' => 'Deleted']" />

        <x-slot:chips>
            @if ($eventFilter)
                <x-nawasara-ui::filter-chip label="Aksi: {{ ucfirst($eventFilter) }}" model="eventFilter" />
            @endif
            @if ($search)
                <x-nawasara-ui::filter-chip label="Cari: {{ $search }}" model="search" />
            @endif
        </x-slot:chips>
    </x-nawasara-ui::filter-bar>

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
                        @if($item->properties && ($item->properties->has('old') || $item->properties->has('attributes')))
                            <button type="button" wire:click="openDetail({{ $item->id }})"
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

    {{-- Detail Modal --}}
    @if ($showDetail && $detailData)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" wire:click.self="closeDetail">
            <div class="bg-white dark:bg-neutral-800 rounded-xl shadow-xl w-full max-w-2xl mx-4 overflow-hidden">
                {{-- Header --}}
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-neutral-700">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-neutral-200">Detail Perubahan</h3>
                        <p class="text-sm text-gray-500 dark:text-neutral-400">
                            {{ $detailData['model'] }}#{{ $detailData['model_id'] }} — {{ $detailData['description'] }}
                        </p>
                    </div>
                    <button wire:click="closeDetail" class="text-gray-400 hover:text-gray-600 dark:hover:text-neutral-300">
                        <x-lucide-x class="size-5" />
                    </button>
                </div>

                {{-- Body --}}
                <div class="px-6 py-4 max-h-[70vh] overflow-y-auto">
                    <div class="grid grid-cols-2 gap-4 mb-4 text-sm">
                        <div>
                            <span class="text-gray-500 dark:text-neutral-400">User:</span>
                            <span class="ml-1 text-gray-800 dark:text-neutral-200">{{ $detailData['user'] }}</span>
                        </div>
                        <div>
                            <span class="text-gray-500 dark:text-neutral-400">Waktu:</span>
                            <span class="ml-1 text-gray-800 dark:text-neutral-200">{{ $detailData['date'] }}</span>
                        </div>
                    </div>

                    @if (!empty($detailData['old']) || !empty($detailData['new']))
                        <table class="w-full text-sm border border-gray-200 dark:border-neutral-700 rounded-lg overflow-hidden">
                            <thead class="bg-gray-50 dark:bg-neutral-700">
                                <tr>
                                    <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-600 dark:text-neutral-300 uppercase">Field</th>
                                    @if (!empty($detailData['old']))
                                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-red-600 dark:text-red-400 uppercase">Sebelum</th>
                                    @endif
                                    <th class="px-4 py-2.5 text-left text-xs font-semibold text-green-600 dark:text-green-400 uppercase">Sesudah</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-neutral-700">
                                @php
                                    $fields = array_unique(array_merge(
                                        array_keys($detailData['old']),
                                        array_keys($detailData['new'])
                                    ));
                                @endphp
                                @foreach ($fields as $field)
                                    @php
                                        $oldVal = $detailData['old'][$field] ?? '-';
                                        $newVal = $detailData['new'][$field] ?? '-';
                                        $changed = $oldVal !== $newVal;
                                    @endphp
                                    <tr class="{{ $changed ? 'bg-yellow-50/50 dark:bg-yellow-900/10' : '' }}">
                                        <td class="px-4 py-2.5 font-medium text-gray-700 dark:text-neutral-300">
                                            {{ Str::headline($field) }}
                                        </td>
                                        @if (!empty($detailData['old']))
                                            <td class="px-4 py-2.5 text-gray-500 dark:text-neutral-400 {{ $changed ? 'line-through' : '' }}">
                                                {{ is_array($oldVal) ? json_encode($oldVal) : $oldVal }}
                                            </td>
                                        @endif
                                        <td class="px-4 py-2.5 text-gray-800 dark:text-neutral-200 {{ $changed ? 'font-medium' : '' }}">
                                            {{ is_array($newVal) ? json_encode($newVal) : $newVal }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <p class="text-sm text-gray-500 dark:text-neutral-400 text-center py-4">Tidak ada detail perubahan.</p>
                    @endif
                </div>

                {{-- Footer --}}
                <div class="px-6 py-3 border-t border-gray-200 dark:border-neutral-700 flex justify-end">
                    <button wire:click="closeDetail"
                        class="py-2 px-4 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 hover:bg-gray-50 dark:bg-neutral-800 dark:border-neutral-700 dark:text-white">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
