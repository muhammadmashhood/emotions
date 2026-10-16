<?php

namespace App\Livewire\Admin\Hospitals;

use App\Domain\Audit\AuditLogger;
use App\Models\Hospital;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class Create extends Component
{
    use WithFileUploads;

    #[Validate('required|string|max:120')]
    public string $name = '';

    #[Validate('nullable|image|max:2048')]
    public ?TemporaryUploadedFile $logo = null;

    #[Validate('boolean')]
    public bool $require_review = false;

    public function save(AuditLogger $audit): void
    {
        $this->authorize('create', Hospital::class);
        $this->validate();

        $hospital = Hospital::query()->create([
            'name' => $this->name,
            'require_review' => $this->require_review,
            'logo_path' => $this->logo?->store('logos', 'public'),
        ]);

        $audit->log('hospital.created', $hospital, ['name' => $hospital->name]);

        session()->flash('status', "{$hospital->name} created. Link its surveys and invite its users.");
        $this->redirect(route('admin.hospitals.show', $hospital), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.admin.hospitals.create');
    }
}
