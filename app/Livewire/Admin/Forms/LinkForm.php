<?php

namespace App\Livewire\Admin\Forms;

use App\Domain\Typeform\ConnectionManager;
use App\Domain\Typeform\Data\FormSummary;
use App\Domain\Typeform\Exceptions\TypeformException;
use App\Domain\Typeform\FormCatalog;
use App\Models\Hospital;
use App\Models\TypeformConnection;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class LinkForm extends Component
{
    public Hospital $hospital;

    public ?TypeformConnection $connection = null;

    #[Url]
    public string $search = '';

    #[Url]
    public string $workspace = '';

    public ?string $selected = null;

    public ?string $apiError = null;

    public function mount(Hospital $hospital, ConnectionManager $connections): void
    {
        $this->authorize('update', $hospital);
        $this->hospital = $hospital;
        $this->connection = $connections->current();
    }

    public function select(string $formId): void
    {
        $this->selected = $formId;
    }

    public function render(FormCatalog $catalog): View
    {
        $workspaces = [];
        /** @var Collection<int, FormSummary> $forms */
        $forms = collect();
        $this->apiError = null;

        if ($this->connection) {
            try {
                $workspaces = $catalog->workspaces($this->connection);
                $forms = $catalog->availableForms($this->connection, $this->hospital, $this->search, $this->workspace ?: null);
            } catch (TypeformException $e) {
                $this->apiError = $e->getMessage();
            }
        }

        return view('livewire.admin.forms.link-form', [
            'workspaces' => $workspaces,
            'forms' => $forms,
            'selectedForm' => $forms->first(fn (FormSummary $f) => $f->id === $this->selected),
            'httpsOk' => str_starts_with((string) config('app.url'), 'https://'),
        ]);
    }
}
