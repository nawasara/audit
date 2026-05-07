<div>
    @php
        $eventOptions = [
            'created' => 'Created',
            'updated' => 'Updated',
            'deleted' => 'Deleted',
        ];
    @endphp

    {{-- Time-window selector row — segmented preset (Hari ini/7d/30d/Custom).
         Sits above the main toolbar so it reads as a primary scope, not a
         secondary filter. Default 7 days keeps initial query bounded. --}}
    <div class="mb-3">
        <x-nawasara-ui::time-window :window="$window" :from="$from" :to="$to" />
    </div>

    {{-- Toolbar — single filter (Aksi/event) + search + export. --}}
    <div class="space-y-2 mb-4">
        <div class="flex flex-col md:flex-row md:flex-nowrap md:items-center gap-2">
            <div class="flex flex-wrap items-center gap-2 shrink-0">
                <x-nawasara-ui::filter-panel
                    label="Filter"
                    :state="['eventFilter' => $eventFilter]"
                    :multiple="['eventFilter']"
                    :labels="['eventFilter' => $eventOptions]">
                    <x-nawasara-ui::filter-group label="Aksi" model="eventFilter" :items="$eventOptions" icon="lucide-zap" />
                </x-nawasara-ui::filter-panel>
            </div>

            {{-- Search zone — fills available space. --}}
            <div class="relative w-full md:flex-1 md:min-w-0">
                <div class="absolute inset-y-0 start-0 flex items-center pointer-events-none ps-3.5">
                    <x-lucide-search class="shrink-0 size-4 text-gray-400 dark:text-neutral-500" />
                </div>
                <input type="text" wire:model.live.debounce.300ms="search"
                    placeholder="Cari user, model, atau deskripsi..."
                    class="h-10 ps-10 pe-4 block w-full border border-gray-200 rounded-lg text-sm focus:border-emerald-600 focus:ring-emerald-600 dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-200 dark:placeholder-neutral-500 dark:focus:ring-neutral-600" />
            </div>

            <div class="flex items-center gap-2 shrink-0">
                <x-nawasara-ui::export-button
                    action="export"
                    tooltip="Ekspor activity log (max 10rb baris)" />
            </div>
        </div>

        {{-- Filter chips teleport target. wire:ignore so Livewire morph
             leaves Alpine-managed chip DOM alone after each filter apply. --}}
        <div wire:ignore data-filter-chips></div>

        @if ($search)
            <div class="flex flex-wrap items-center gap-2">
                <x-nawasara-ui::filter-chip label="Cari: {{ $search }}" model="search" />
            </div>
        @endif
    </div>

    {{-- No stickyLast: activity-log is read-only, no action column to pin. --}}
    <x-nawasara-ui::table
        :headers="['#', 'User', 'Model', 'Aksi', 'Deskripsi', 'Tanggal']"
        :title="'Activity Log ('.$this->items->total().' entries)'">
        <x-slot:table>
            @forelse ($this->items as $item)
                <tr wire:key="activity-{{ $item->id }}">
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
                        @if ($item->properties && ($item->properties->has('old') || $item->properties->has('attributes')))
                            <x-nawasara-ui::button variant="link" color="primary" size="sm"
                                wire:click="openDetail({{ $item->id }})"
                                @click="$dispatch('open-modal', {id: 'audit-detail', loading: true})"
                                class="ml-1 text-xs">
                                detail
                            </x-nawasara-ui::button>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 dark:text-neutral-200">
                        {{ $item->created_at->format('d M Y H:i') }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">
                        @if ($search || ! empty($eventFilter) || $window !== '7d' || $from || $to)
                            <x-nawasara-ui::empty-state
                                icon="lucide-search-x"
                                title="Tidak ada activity yang cocok"
                                description="Coba ubah periode/filter atau hapus search keyword."
                                variant="filter"
                                inline />
                        @else
                            <x-nawasara-ui::empty-state
                                icon="lucide-history"
                                title="Belum ada activity 7 hari terakhir"
                                description="Pilih periode lebih panjang atau Custom untuk melihat data lebih lama."
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

    {{-- Detail Modal --}}
    <x-nawasara-ui::modal id="audit-detail" maxWidth="2xl" title="Detail Perubahan"
        :subtitle="$detailData ? $detailData['model'].'#'.$detailData['model_id'].' — '.$detailData['description'] : null">
        @if ($detailData)
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
                            $fields = array_unique(array_merge(array_keys($detailData['old']), array_keys($detailData['new'])));
                        @endphp
                        @foreach ($fields as $field)
                            @php
                                $oldVal = $detailData['old'][$field] ?? '-';
                                $newVal = $detailData['new'][$field] ?? '-';
                                $changed = $oldVal !== $newVal;
                            @endphp
                            <tr class="{{ $changed ? 'bg-yellow-50/50 dark:bg-yellow-900/10' : '' }}">
                                <td class="px-4 py-2.5 font-medium text-gray-700 dark:text-neutral-300">{{ Str::headline($field) }}</td>
                                @if (!empty($detailData['old']))
                                    <td class="px-4 py-2.5 text-gray-500 dark:text-neutral-400 {{ $changed ? 'line-through' : '' }}">{{ is_array($oldVal) ? json_encode($oldVal) : $oldVal }}</td>
                                @endif
                                <td class="px-4 py-2.5 text-gray-800 dark:text-neutral-200 {{ $changed ? 'font-medium' : '' }}">{{ is_array($newVal) ? json_encode($newVal) : $newVal }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p class="text-sm text-gray-500 dark:text-neutral-400 text-center py-4">Tidak ada detail perubahan.</p>
            @endif

            <x-slot:footer>
                <x-nawasara-ui::button color="neutral" variant="outline" wire:click="closeDetail">
                    Tutup
                </x-nawasara-ui::button>
            </x-slot:footer>
        @endif
    </x-nawasara-ui::modal>
</div>
