<?php

namespace App\Livewire\Hospital\Concerns;

use App\Domain\Dashboard\DashboardFilters;
use App\Models\Branch;
use App\Models\Form;
use App\Models\Hospital;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

/**
 * Query-string persisted filters shared by the dashboard and the evaluations list.
 */
trait HasDashboardFilters
{
    #[Url(except: '90d')]
    public string $period = '90d';

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    #[Url(except: '')]
    public string $form = '';

    #[Url(except: '')]
    public string $branch = '';

    protected function filters(Hospital $hospital): DashboardFilters
    {
        $form = $this->selectedForm($hospital);
        $branch = $this->branch !== '' ? Branch::query()->where('hospital_id', $hospital->id)->find((int) $this->branch) : null;

        return DashboardFilters::fromRequest($this->period, $this->from ?: null, $this->to ?: null, $form?->id, $branch?->id);
    }

    protected function selectedForm(Hospital $hospital): ?Form
    {
        if ($this->form === '') {
            return null;
        }

        return Form::withTrashed()->where('hospital_id', $hospital->id)->where('uuid', $this->form)->first();
    }

    /** @return Collection<int, Form> */
    protected function availableForms(Hospital $hospital): Collection
    {
        return Form::withTrashed()->where('hospital_id', $hospital->id)->orderBy('title')->get(['id', 'uuid', 'title', 'status']);
    }

    /** @return Collection<int, Branch> */
    protected function availableBranches(Hospital $hospital): Collection
    {
        return Branch::query()->where('hospital_id', $hospital->id)->where('is_active', true)->orderBy('name')->get(['id', 'name']);
    }

    /** @return array<string, string> */
    public function filterQuery(): array
    {
        return array_filter([
            'period' => $this->period !== '90d' ? $this->period : null,
            'from' => $this->from ?: null,
            'to' => $this->to ?: null,
            'form' => $this->form ?: null,
            'branch' => $this->branch ?: null,
        ]);
    }
}
