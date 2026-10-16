<?php

namespace App\Livewire\Hospital\Evaluations;

use App\Domain\Dashboard\EvaluationListQuery;
use App\Enums\Rag;
use App\Enums\SubmissionStatus;
use App\Livewire\Hospital\Concerns\HasDashboardFilters;
use App\Models\Hospital;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use HasDashboardFilters, WithPagination;

    public Hospital $hospital;

    public bool $viewingAsAdmin = false;

    #[Url(except: '')]
    public string $rag = '';

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: 'date')]
    public string $sort = 'date';

    #[Url(except: 'desc')]
    public string $direction = 'desc';

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

    public function updated(string $property): void
    {
        if (in_array($property, ['period', 'from', 'to', 'form', 'branch', 'rag', 'search', 'status', 'sort', 'direction'], true)) {
            $this->resetPage();
        }
    }

    public function sortBy(string $column): void
    {
        if ($this->sort === $column) {
            $this->direction = $this->direction === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sort = $column;
            $this->direction = 'desc';
        }
        $this->resetPage();
    }

    public function exportUrl(): string
    {
        $params = $this->filterQuery() + array_filter([
            'rag' => $this->rag ?: null,
            'search' => $this->search ?: null,
            'status' => $this->status ?: null,
            'sort' => $this->sort !== 'date' ? $this->sort : null,
            'direction' => $this->direction !== 'desc' ? $this->direction : null,
            'hospital' => $this->viewingAsAdmin ? $this->hospital->uuid : null,
        ]);

        return route('evaluations.export', $params);
    }

    public function render(): View
    {
        /** @var User $user */
        $user = auth()->user();
        $filters = $this->filters($this->hospital);

        $evaluations = (new EvaluationListQuery($user))
            ->build($this->hospital, $filters, $this->rag ?: null, $this->search, $this->status ?: null, $this->sort, $this->direction)
            ->paginate(25);

        return view('livewire.hospital.evaluations.index', [
            'evaluations' => $evaluations,
            'filters' => $filters,
            'forms' => $this->availableForms($this->hospital),
            'branches' => $this->availableBranches($this->hospital),
            'rags' => Rag::cases(),
            'statuses' => SubmissionStatus::cases(),
            'isAdmin' => $user->isSuperAdmin(),
        ]);
    }
}
