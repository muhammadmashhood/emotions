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
            'trend' => $trend = $query->trend($this->hospital, $filters),
            'overall' => self::combine($trend),
            'previous' => self::combine($query->trend($this->hospital, $filters->previous())),
            'sections' => $selectedForm ? $query->sectionAverages($selectedForm, $filters) : null,
            'weakest' => $selectedForm ? $query->weakestQuestions($selectedForm, $filters) : null,
            'strongest' => $selectedForm ? $query->strongestQuestions($selectedForm, $filters) : null,
            'recent' => $query->recent($this->hospital, $filters),
            'distribution' => $query->distribution($this->hospital, $filters),
            'kpis' => $query->kpis($this->hospital, $filters),
            'ranking' => $query->facilityRanking($this->hospital, $filters),
            'heatmap' => $query->heatmap($this->hospital, $filters, $selectedForm),
            'ragGreen' => $selectedForm?->settings->ragGreenMin ?? 80,
            'ragAmber' => $selectedForm?->settings->ragAmberMin ?? 60,
        ]);
    }

    /**
     * Collapse the per-survey trend series into one evaluation-weighted line.
     *
     * @param  array{labels: list<string>, series: list<array{data: list<float|null>, counts: list<int>}>}  $trend
     * @return array{data: list<float|null>, counts: list<int>}
     */
    public static function combine(array $trend): array
    {
        $data = [];
        $counts = [];
        foreach (array_keys($trend['labels']) as $i) {
            $n = 0;
            $sum = 0.0;
            foreach ($trend['series'] as $series) {
                $count = $series['counts'][$i] ?? 0;
                if ($count > 0 && $series['data'][$i] !== null) {
                    $n += $count;
                    $sum += $series['data'][$i] * $count;
                }
            }
            $data[] = $n > 0 ? round($sum / $n, 1) : null;
            $counts[] = $n;
        }

        return ['data' => $data, 'counts' => $counts];
    }
}
