<?php

namespace Nawasara\Audit\Livewire\ActivityLog\Section;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Spatie\Activitylog\Models\Activity;

class Table extends Component
{
    use WithPagination;

    public string $search = '';
    public string $eventFilter = '';

    // Detail modal
    public bool $showDetail = false;
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
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('description', 'like', "%{$this->search}%")
                      ->orWhere('subject_type', 'like', "%{$this->search}%")
                      ->orWhereHas('causer', fn ($q) => $q->where('name', 'like', "%{$this->search}%"));
                });
            })
            ->when($this->eventFilter, fn ($q) => $q->where('event', $this->eventFilter))
            ->latest()
            ->paginate(15);
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

        $this->showDetail = true;
    }

    public function closeDetail()
    {
        $this->showDetail = false;
        $this->detailData = null;
    }

    public function render()
    {
        return view('nawasara-audit::livewire.pages.activity-log.section.table');
    }
}
