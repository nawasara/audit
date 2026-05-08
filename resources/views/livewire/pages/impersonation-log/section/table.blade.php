<div>
    @php
        $typeOptions = ['webmail' => 'Webmail', 'cpanel' => 'cPanel'];
        $statusOptions = ['issued' => 'Issued', 'failed' => 'Failed', 'rejected' => 'Rejected'];
    @endphp

    {{-- Page header — title + count + time-window selector. Mirror
         LoginHistory pattern; impersonation log is read-only audit data
         so no primary CTA button. --}}
    <x-nawasara-ui::page-header
        title="Impersonation Log"
        description="Riwayat akses admin ke webmail/cPanel atas nama user lain. Semua launch-as event dicatat dengan alasan, IP, dan user agent untuk akuntabilitas."
        :count="$this->items->total().' event'">
        <x-nawasara-ui::time-window :window="$window" :from="$from" :to="$to" />
    </x-nawasara-ui::page-header>

    {{-- Toolbar — Filter (Type + Status + Actor) + search + export. --}}
    <div class="space-y-2 mb-4">
        <div class="flex flex-col md:flex-row md:flex-nowrap md:items-center gap-2">
            <div class="flex flex-wrap items-center gap-2 shrink-0">
                <x-nawasara-ui::filter-panel
                    label="Filter"
                    :state="['typeFilter' => $typeFilter, 'statusFilter' => $statusFilter]"
                    :multiple="['typeFilter', 'statusFilter']"
                    :labels="['typeFilter' => $typeOptions, 'statusFilter' => $statusOptions]"
                    :dimensions="['typeFilter' => 'Type', 'statusFilter' => 'Status']">
                    <x-nawasara-ui::filter-group label="Type" model="typeFilter" :items="$typeOptions" icon="lucide-shield-check" />
                    <x-nawasara-ui::filter-group label="Status" model="statusFilter" :items="$statusOptions" icon="lucide-circle-check" />
                </x-nawasara-ui::filter-panel>

                {{-- Actor (admin) selector — single-select dropdown karena
                     biasanya audit query "siapa admin X" specific. --}}
                @if (! empty($this->actorOptions))
                    <select wire:model.live="actorFilter"
                        class="h-10 rounded-lg border-gray-200 dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-200 text-sm focus:border-emerald-600 focus:ring-emerald-600">
                        <option value="">Semua Admin</option>
                        @foreach ($this->actorOptions as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                @endif
            </div>

            <x-nawasara-ui::search-input model="search" placeholder="Cari target email/akun, alasan, atau IP..." />

            <div class="flex items-center gap-2 shrink-0">
                <x-nawasara-ui::export-button
                    action="export"
                    tooltip="Ekspor impersonation log (max 10rb baris, sesuai filter)" />
            </div>
        </div>

        <div wire:ignore data-filter-chips></div>

        @if ($search)
            <div class="flex flex-wrap items-center gap-2">
                <x-nawasara-ui::filter-chip label="Cari: {{ $search }}" model="search" />
            </div>
        @endif
    </div>

    {{-- Table — read-only audit, no action column kecuali "Detail" untuk
         lihat full reason + UA. --}}
    <x-nawasara-ui::table stickyLast
        :headers="['Waktu', 'Type', 'Admin', 'Target', 'Status', 'Alasan', 'IP', '']">
        <x-slot:table>
            @forelse ($this->items as $row)
                <tr wire:key="impersonation-{{ $row->type }}-{{ $row->id }}">
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 dark:text-neutral-200">
                        {{ \Carbon\Carbon::parse($row->created_at)->format('d M Y H:i:s') }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        @if ($row->type === 'webmail')
                            <x-nawasara-ui::badge color="info">
                                <x-lucide-mail class="size-3 inline" /> Webmail
                            </x-nawasara-ui::badge>
                        @else
                            <x-nawasara-ui::badge color="warning">
                                <x-lucide-server class="size-3 inline" /> cPanel
                            </x-nawasara-ui::badge>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 dark:text-neutral-200">
                        {{ $row->actor?->name ?? '#'.$row->acted_by_user_id }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-700 dark:text-neutral-300">
                        {{ $row->target ?? '-' }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        @if ($row->status === 'issued')
                            <x-nawasara-ui::badge color="success" dot>Issued</x-nawasara-ui::badge>
                        @elseif ($row->status === 'failed')
                            <x-nawasara-ui::badge color="danger" dot>Failed</x-nawasara-ui::badge>
                        @else
                            <x-nawasara-ui::badge color="neutral" dot>{{ ucfirst($row->status) }}</x-nawasara-ui::badge>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-neutral-400 max-w-xs truncate" title="{{ $row->reason }}">
                        {{ \Illuminate\Support\Str::limit($row->reason ?? '-', 50) }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-500 dark:text-neutral-400">
                        {{ $row->ip ?? '-' }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                        <button type="button" wire:click="openDetail('{{ $row->type }}', {{ $row->id }})"
                            class="text-emerald-700 dark:text-emerald-400 hover:underline text-xs font-medium">
                            Detail
                        </button>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8">
                        @if ($search || ! empty($typeFilter) || ! empty($statusFilter) || $actorFilter || $window !== '7d' || $from || $to)
                            <x-nawasara-ui::empty-state
                                icon="lucide-search-x"
                                title="Tidak ada event yang cocok"
                                description="Coba ubah periode/filter atau hapus search keyword."
                                variant="filter"
                                inline />
                        @else
                            <x-nawasara-ui::empty-state
                                icon="lucide-shield-check"
                                title="Belum ada impersonation event 7 hari terakhir"
                                description="Aktivitas admin akan tercatat di sini setiap kali mereka launch webmail/cPanel atas nama user lain."
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

    {{-- Detail Modal — full context: alasan lengkap, UA, error message
         (kalau status=failed). User-agent string panjang ada di sini
         instead of table column supaya table tidak overflow. --}}
    <x-nawasara-ui::modal id="impersonation-detail" maxWidth="2xl" title="Detail Impersonation Event">
        @if ($this->detail)
            @php $d = $this->detail; @endphp
            <div class="space-y-4 text-sm">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <span class="text-gray-500 dark:text-neutral-400">Type:</span>
                        <span class="font-medium">
                            @if ($d->type === 'webmail')
                                <x-nawasara-ui::badge color="info">Webmail</x-nawasara-ui::badge>
                            @else
                                <x-nawasara-ui::badge color="warning">cPanel</x-nawasara-ui::badge>
                            @endif
                        </span>
                    </div>
                    <div>
                        <span class="text-gray-500 dark:text-neutral-400">Status:</span>
                        <span class="font-medium">
                            @if ($d->status === 'issued')
                                <x-nawasara-ui::badge color="success">Issued</x-nawasara-ui::badge>
                            @elseif ($d->status === 'failed')
                                <x-nawasara-ui::badge color="danger">Failed</x-nawasara-ui::badge>
                            @else
                                <x-nawasara-ui::badge color="neutral">{{ ucfirst($d->status) }}</x-nawasara-ui::badge>
                            @endif
                        </span>
                    </div>
                    <div>
                        <span class="text-gray-500 dark:text-neutral-400">Waktu:</span>
                        <span class="font-medium">{{ \Carbon\Carbon::parse($d->created_at)->format('d M Y H:i:s') }}</span>
                    </div>
                    <div>
                        <span class="text-gray-500 dark:text-neutral-400">Admin:</span>
                        <span class="font-medium">{{ $d->actor?->name ?? '#'.($d->acted_by_user_id ?? '?') }}</span>
                    </div>
                    <div class="col-span-2">
                        <span class="text-gray-500 dark:text-neutral-400">Target:</span>
                        <span class="font-mono font-medium">{{ $d->target }}</span>
                        @if ($d->type === 'webmail' && isset($d->target_user))
                            <span class="text-xs text-gray-500 dark:text-neutral-400">
                                (linked to user: {{ $d->target_user->name }})
                            </span>
                        @endif
                    </div>
                    @if ($d->type === 'cpanel')
                        <div>
                            <span class="text-gray-500 dark:text-neutral-400">Domain:</span>
                            <span class="font-medium">{{ $d->domain ?? '-' }}</span>
                        </div>
                        <div>
                            <span class="text-gray-500 dark:text-neutral-400">Instance:</span>
                            <span class="font-mono font-medium">{{ $d->instance ?? '-' }}</span>
                        </div>
                    @endif
                    <div>
                        <span class="text-gray-500 dark:text-neutral-400">IP:</span>
                        <span class="font-mono font-medium">{{ $d->ip ?? '-' }}</span>
                    </div>
                </div>

                @if ($d->reason)
                    <div class="border-t border-gray-200 dark:border-neutral-700 pt-4">
                        <p class="text-gray-500 dark:text-neutral-400 mb-1">Alasan akses:</p>
                        <p class="text-gray-800 dark:text-neutral-200 whitespace-pre-wrap">{{ $d->reason }}</p>
                    </div>
                @endif

                @if ($d->error)
                    <div class="border-t border-gray-200 dark:border-neutral-700 pt-4">
                        <p class="text-gray-500 dark:text-neutral-400 mb-1">Error:</p>
                        <p class="text-red-600 dark:text-red-400 font-mono text-xs whitespace-pre-wrap break-all">{{ $d->error }}</p>
                    </div>
                @endif

                @if ($d->user_agent)
                    <div class="border-t border-gray-200 dark:border-neutral-700 pt-4">
                        <p class="text-gray-500 dark:text-neutral-400 mb-1">User Agent:</p>
                        <p class="font-mono text-xs text-gray-700 dark:text-neutral-300 break-all">{{ $d->user_agent }}</p>
                    </div>
                @endif
            </div>
        @endif

        <x-slot:footer>
            <x-nawasara-ui::button color="neutral" variant="outline" wire:click="closeDetail">Tutup</x-nawasara-ui::button>
        </x-slot:footer>
    </x-nawasara-ui::modal>
</div>
