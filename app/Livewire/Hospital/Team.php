<?php

namespace App\Livewire\Hospital;

use App\Models\Hospital;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Team extends Component
{
    public Hospital $hospital;

    public function mount(): void
    {
        /** @var User $user */
        $user = auth()->user();
        $hospital = $user->hospital;
        abort_unless($hospital instanceof Hospital, 404);
        $this->authorize('manageUsers', $hospital);
        $this->hospital = $hospital;
    }

    public function render(): View
    {
        return view('livewire.hospital.team');
    }
}
