<?php

namespace App\Livewire\Admin\Hospitals;

use App\Domain\Dashboard\DashboardFilters;
use App\Domain\Dashboard\DashboardQuery;
use App\Models\Hospital;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Hospital accounts as cards (eMotion Admin design): counts plus the 30-day
 * average score with its change against the previous 30 days.
 */
#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    public function render(): View
    {
        /** @var User $user */
        $user = auth()->user();
        $scores = (new DashboardQuery($user))->perHospital(DashboardFilters::fromRequest('30d'));

        $hospitals = Hospital::query()
            ->withCount([
                'users',
                'forms' => fn ($q) => $q->active(),
                'branches' => fn ($q) => $q->where('is_active', true),
            ])
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(24);

        return view('livewire.admin.hospitals.index', [
            'hospitals' => $hospitals,
            'scores' => $scores,
        ]);
    }
}
