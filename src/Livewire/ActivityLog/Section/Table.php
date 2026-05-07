<?php

namespace Nawasara\Audit\Livewire\ActivityLog\Section;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Nawasara\Ui\Livewire\Concerns\HasExport;
use Nawasara\Ui\Livewire\Concerns\HasTimeWindow;
use Spatie\Activitylog\Models\Activity;

class Table extends Component
{
    use HasExport;
    use HasTimeWindow;
    use WithPagination;

    public string $search = '';

    /**
     * Event-type filter as a multi-select array (e.g. ['created', 'updated']).
     * Empty array == no filter. Was previously a single string; the filter
     * panel now treats this as multi-select for parity with DNS records.
     *
     * @var array<int, string>
     */
    public array $eventFilter = [];

    // Detail modal
    public ?array $detailData = null;

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedEventFilter()
    {
        $this->resetPage();
    }

    #[Computed]
    public function items()
    {
        return Activity::query()
            ->with('causer')
            ->tap(fn ($q) => $this->applyTimeWindow($q, 'created_at'))
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('description', 'like', "%{$this->search}%")
                      ->orWhere('subject_type', 'like', "%{$this->search}%")
                      ->orWhereHas('causer', fn ($q) => $q->where('name', 'like', "%{$this->search}%"));
                });
            })
            ->when(! empty($this->eventFilter), fn ($q) => $q->whereIn('event', $this->eventFilter))
            ->latest()
            ->paginate(15);
    }

    /**
     * Export filename base — timestamp + extension appended by HasExport.
     */
    protected function exportFilename(): string
    {
        return 'activity-log';
    }

    /**
     * Export FULL audit log (ignoring active filter/search) per spec.
     * Old/new property snapshots are flattened to JSON strings so they fit
     * one cell each; viewers needing diff details can use the in-app
     * detail modal.
     */
    protected function exportData(): iterable
    {
        return Activity::query()
            ->with('causer')
            ->latest()
            ->limit(10000) // hard cap so xlsx generation stays bounded
            ->get()
            ->map(fn (Activity $a) => [
                'ID' => $a->id,
                'Waktu' => optional($a->created_at)->format('Y-m-d H:i:s'),
                'User' => $a->causer?->name ?? 'System',
                'Event' => $a->event,
                'Model' => class_basename($a->subject_type ?? '-'),
                'Model ID' => $a->subject_id,
                'Deskripsi' => $a->description,
                'Old' => $a->properties && $a->properties->has('old')
                    ? json_encode($a->properties['old'], JSON_UNESCAPED_UNICODE)
                    : null,
                'New' => $a->properties && $a->properties->has('attributes')
                    ? json_encode($a->properties['attributes'], JSON_UNESCAPED_UNICODE)
                    : null,
                'Log Name' => $a->log_name,
            ]);
    }

    public function openDetail($id)
    {
        $activity = Activity::with('causer')->findOrFail($id);

        $this->detailData = [
            'id' => $activity->id,
            'user' => $activity->causer?->name ?? 'System',
            'event' => $activity->event,
            'model' => class_basename($activity->subject_type ?? '-'),
            'model_id' => $activity->subject_id,
            'description' => $activity->description,
            'old' => $activity->properties['old'] ?? [],
            'new' => $activity->properties['attributes'] ?? [],
            'date' => $activity->created_at->format('d M Y H:i:s'),
        ];

        $this->dispatch('modal-open:audit-detail');
    }

    public function closeDetail()
    {
        $this->dispatch('modal-close:audit-detail');
        $this->detailData = null;
    }

    public function render()
    {
        return view('nawasara-audit::livewire.pages.activity-log.section.table');
    }
}
