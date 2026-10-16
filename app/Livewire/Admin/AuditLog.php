<?php

namespace App\Livewire\Admin;

use App\Models\AuditLog as AuditLogModel;
use App\Models\Hospital;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class AuditLog extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $action = '';

    #[Url(except: '')]
    public string $hospital = '';

    #[Url(except: '')]
    public string $user = '';

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    public ?int $expanded = null;

    public function updated(string $property): void
    {
        if (in_array($property, ['action', 'hospital', 'user', 'from', 'to'], true)) {
            $this->resetPage();
        }
    }

    public function toggle(int $id): void
    {
        $this->expanded = $this->expanded === $id ? null : $id;
    }

    public function render(): View
    {
        $logs = AuditLogModel::query()
            ->with(['user:id,name,email', 'hospital:id,name,uuid'])
            ->when($this->action !== '', fn ($q) => $q->where('action', 'like', $this->action.'%'))
            ->when($this->hospital !== '', fn ($q) => $q->whereHas('hospital', fn ($h) => $h->where('uuid', $this->hospital)))
            ->when($this->user !== '', fn ($q) => $q->whereHas('user', fn ($u) => $u->where('email', 'like', '%'.$this->user.'%')->orWhere('name', 'like', '%'.$this->user.'%')))
            ->when($this->from !== '', fn ($q) => $q->where('created_at', '>=', $this->from.' 00:00:00'))
            ->when($this->to !== '', fn ($q) => $q->where('created_at', '<=', $this->to.' 23:59:59'))
            ->orderByDesc('created_at')->orderByDesc('id')
            ->paginate(50);

        $actionPrefixes = AuditLogModel::query()->selectRaw("SUBSTRING_INDEX(action, '.', 1) as prefix")->distinct()->orderBy('prefix')->pluck('prefix');

        return view('livewire.admin.audit.index', [
            'logs' => $logs,
            'hospitals' => Hospital::query()->orderBy('name')->get(['id', 'uuid', 'name']),
            'actionPrefixes' => $actionPrefixes,
        ]);
    }
}
