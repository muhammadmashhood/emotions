<?php

namespace App\Livewire\Admin\Hospitals;

use App\Models\Hospital;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $hospitals = Hospital::query()
            ->withCount(['users', 'forms' => fn ($q) => $q->active()])
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
            ->orderBy('name')
            ->paginate(25);

        return view('livewire.admin.hospitals.index', ['hospitals' => $hospitals]);
    }
}
