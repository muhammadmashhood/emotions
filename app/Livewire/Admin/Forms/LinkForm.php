<?php

namespace App\Livewire\Admin\Forms;

use App\Domain\Forms\FormLinker;
use App\Domain\Typeform\ConnectionManager;
use App\Domain\Typeform\Data\FormSummary;
use App\Domain\Typeform\Exceptions\TypeformException;
use App\Domain\Typeform\FormCatalog;
use App\Enums\WebhookStatus;
use App\Models\Hospital;
use App\Models\TypeformConnection;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
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

    public function link(FormLinker $linker): void
    {
        if ($this->selected === null || $this->connection === null) {
            return;
        }

        /** @var User $user */
        $user = auth()->user();

        try {
            $form = $linker->link($this->hospital, $this->connection, $this->selected, $user);
        } catch (ValidationException $e) {
            $this->addError('selected', collect($e->errors())->flatten()->first() ?? 'Could not link this form.');

            return;
        } catch (TypeformException $e) {
            $this->addError('selected', 'Typeform error: '.$e->getMessage());

            return;
        }

        session()->flash('status', $form->webhook_status === WebhookStatus::Active
            ? "{$form->title} linked. Review the mapping below."
            : "{$form->title} linked, but the webhook could not be registered: {$form->webhook_last_error}. Use \"Repair webhook\" on the form page.");

        $this->redirect(route('admin.forms.mapping', $form), navigate: true);
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
