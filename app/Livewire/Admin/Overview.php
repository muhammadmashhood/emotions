<?php

namespace App\Livewire\Admin;

use App\Models\Hospital;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Overview extends Component
{
    public function render(): View
    {
        return view('livewire.admin.overview', [
            'hospitals' => Hospital::query()->withCount(['users', 'forms' => fn ($q) => $q->active()])->orderBy('name')->get(),
        ]);
    }
}
