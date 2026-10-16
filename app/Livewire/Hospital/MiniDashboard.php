<?php

namespace App\Livewire\Hospital;

use App\Domain\Dashboard\DashboardFilters;
use App\Domain\Dashboard\DashboardQuery;
use App\Models\Hospital;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Compact 90-day summary used on the admin hospital page.
 */
class MiniDashboard extends Component
{
    public Hospital $hospital;

    public function render(): View
    {
        /** @var User $user */
        $user = auth()->user();
        $query = new DashboardQuery($user);
        $filters = DashboardFilters::fromRequest('90d');

        return view('livewire.hospital.mini-dashboard', [
            'headline' => $query->headline($this->hospital, $filters),
            'distribution' => $query->distribution($this->hospital, $filters),
            'recent' => $query->recent($this->hospital, $filters, 5),
        ]);
    }
}
