<?php

namespace App\Livewire\Hospital;

use App\Domain\Dashboard\DashboardQuery;
use App\Livewire\Hospital\Concerns\HasDashboardFilters;
use App\Models\Hospital;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The hospital dashboard (spec 9.1). `/dashboard` for hospital users; `/hospitals/{uuid}/dashboard` for admins.
 */
#[Layout('layouts.app')]
class Dashboard extends Component
{
    use HasDashboardFilters;

    public Hospital $hospital;

    public bool $viewingAsAdmin = false;

    public function mount(?Hospital $hospital = null): void
    {
        /** @var User $user */
        $user = auth()->user();

        // Without a route parameter the container injects an empty model; treat it as "no hospital given".
        if ($hospital !== null && $hospital->exists) {
            $this->authorize('view', $hospital);
            $this->hospital = $hospital;
            $this->viewingAsAdmin = $user->isSuperAdmin();
        } else {
            abort_unless($user->hospital instanceof Hospital, 404);
            $this->hospital = $user->hospital;
        }
    }

    public function setPeriod(string $period): void
    {
        $this->period = $period;
        if ($period !== 'custom') {
            $this->from = '';
            $this->to = '';
        }
    }

    public function render(): View
    {
        /** @var User $user */
        $user = auth()->user();
        $query = new DashboardQuery($user);
        $filters = $this->filters($this->hospital);
        $selectedForm = $this->selectedForm($this->hospital);
        if ($selectedForm) {
            $selectedForm->load('currentVersion');
        }

        return view('livewire.hospital.dashboard', [
            'filters' => $filters,
            'forms' => $this->availableForms($this->hospital),
            'branches' => $this->availableBranches($this->hospital),
            'selectedForm' => $selectedForm,
            'headline' => $query->headline($this->hospital, $filters),
            'trend' => $query->trend($this->hospital, $filters),
            'sections' => $selectedForm ? $query->sectionAverages($selectedForm, $filters) : null,
            'weakest' => $selectedForm ? $query->weakestQuestions($selectedForm, $filters) : null,
            'compliance' => $selectedForm ? $query->yesNoCompliance($selectedForm, $filters) : null,
            'recent' => $query->recent($this->hospital, $filters),
            'distribution' => $query->distribution($this->hospital, $filters),
            'kpis' => $query->kpis($this->hospital, $filters),
            'ranking' => $query->facilityRanking($this->hospital, $filters),
            'heatmap' => $query->heatmap($this->hospital, $filters, $selectedForm),
            'ragGreen' => $selectedForm?->settings->ragGreenMin ?? 80,
            'ragAmber' => $selectedForm?->settings->ragAmberMin ?? 60,
        ]);
    }
}
