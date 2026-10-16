<?php

namespace App\Livewire\Admin\Hospitals;

use App\Domain\Audit\AuditLogger;
use App\Domain\Typeform\ConnectionManager;
use App\Domain\Typeform\Data\Workspace;
use App\Domain\Typeform\Exceptions\TypeformException;
use App\Domain\Typeform\FormCatalog;
use App\Models\Hospital;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class Show extends Component
{
    use WithFileUploads;

    public Hospital $hospital;

    #[Url]
    public string $tab = 'overview';

    public string $name = '';

    public bool $is_active = true;

    public bool $require_review = false;

    public ?TemporaryUploadedFile $logo = null;

    public string $workspace = '';

    public function mount(Hospital $hospital): void
    {
        $this->authorize('update', $hospital);
        $this->hospital = $hospital;
        $this->name = $hospital->name;
        $this->is_active = $hospital->is_active;
        $this->require_review = $hospital->require_review;
        $this->workspace = (string) ($hospital->typeform_workspace_id ?? '');
    }

    public function saveSettings(AuditLogger $audit): void
    {
        $this->authorize('update', $this->hospital);
        $this->validate([
            'name' => 'required|string|max:120',
            'is_active' => 'boolean',
            'require_review' => 'boolean',
            'logo' => 'nullable|image|max:2048',
        ]);

        $before = $this->hospital->only(['name', 'is_active', 'require_review', 'typeform_workspace_id']);
        $workspaces = collect($this->workspaces())->keyBy('id');
        $this->hospital->fill([
            'name' => $this->name,
            'is_active' => $this->is_active,
            'require_review' => $this->require_review,
            'typeform_workspace_id' => $this->workspace !== '' ? $this->workspace : null,
            'typeform_workspace_name' => $this->workspace !== '' ? ($workspaces->get($this->workspace)->name ?? $this->hospital->typeform_workspace_name) : null,
        ]);
        if ($this->logo) {
            $this->hospital->logo_path = $this->logo->store('logos', 'public');
            $this->logo = null;
        }
        $this->hospital->save();

        $audit->log('hospital.updated', $this->hospital, ['before' => $before, 'after' => $this->hospital->only(['name', 'is_active', 'require_review', 'typeform_workspace_id'])]);

        session()->flash('status', 'Settings saved.');
        $this->dispatch('$refresh');
    }

    /**
     * @return list<Workspace>
     */
    private function workspaces(): array
    {
        $connection = app(ConnectionManager::class)->current();
        if ($connection === null) {
            return [];
        }

        try {
            return app(FormCatalog::class)->workspaces($connection);
        } catch (TypeformException) {
            return [];
        }
    }

    public function render(): View
    {
        $this->hospital->loadCount(['users', 'forms' => fn ($q) => $q->active(), 'submissions']);

        return view('livewire.admin.hospitals.show', [
            'forms' => $this->hospital->forms()->with('currentVersion')->withCount('submissions')->orderBy('title')->get(),
            'workspaces' => $this->tab === 'settings' ? $this->workspaces() : [],
        ]);
    }
}
