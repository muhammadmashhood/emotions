<?php

namespace App\Livewire\Admin\Forms;

use App\Domain\Audit\AuditLogger;
use App\Domain\Forms\FormLinker;
use App\Domain\Forms\WebhookRegistrar;
use App\Enums\WebhookStatus;
use App\Jobs\BackfillFormResponses;
use App\Jobs\RescoreFormSubmissions;
use App\Models\Form;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Show extends Component
{
    public Form $form;

    public float $rag_green_min = 80;

    public float $rag_amber_min = 60;

    public float $yes_no_points = 10;

    public bool $include_partial = false;

    public bool $useLatestMapping = false;

    /** none | hidden | field */
    public string $branchSourceType = 'none';

    public string $branchHiddenKey = 'branch';

    public string $branchFieldId = '';

    public function mount(Form $form): void
    {
        $this->authorize('manage', $form);
        $this->form = $form;
        $settings = $form->settings;
        $this->rag_green_min = $settings->ragGreenMin;
        $this->rag_amber_min = $settings->ragAmberMin;
        $this->yes_no_points = $settings->yesNoPoints;
        $this->include_partial = $settings->includePartial;

        $source = $settings->branchSource;
        if ($source !== null) {
            $this->branchSourceType = $source['type'];
            $this->branchHiddenKey = (string) ($source['key'] ?? 'branch');
            $this->branchFieldId = (string) ($source['field_id'] ?? '');
        }
    }

    public function saveSettings(AuditLogger $audit): void
    {
        $this->validate([
            'rag_green_min' => 'required|numeric|min:0|max:100|gte:rag_amber_min',
            'rag_amber_min' => 'required|numeric|min:0|max:100',
            'yes_no_points' => 'required|numeric|min:1|max:100',
            'include_partial' => 'boolean',
            'branchSourceType' => 'required|in:none,hidden,field',
            'branchHiddenKey' => 'required_if:branchSourceType,hidden|nullable|string|max:60',
            'branchFieldId' => 'required_if:branchSourceType,field|nullable|string|max:60',
        ]);

        $branchSource = match ($this->branchSourceType) {
            'hidden' => ['type' => 'hidden', 'key' => trim($this->branchHiddenKey) ?: 'branch'],
            'field' => ['type' => 'field', 'field_id' => $this->branchFieldId],
            default => null,
        };

        $before = $this->form->settings->toArray();
        $this->form->settings = $this->form->settings->with([
            'rag_green_min' => $this->rag_green_min,
            'rag_amber_min' => $this->rag_amber_min,
            'yes_no_points' => $this->yes_no_points,
            'include_partial' => $this->include_partial,
            'branch_source' => $branchSource,
        ]);
        $this->form->save();

        $audit->log('form.settings_updated', $this->form, ['before' => $before, 'after' => $this->form->settings->toArray()], $this->form->hospital_id);
        session()->flash('status', 'Settings saved. Recalculate scores to apply new thresholds or points to existing evaluations.');
    }

    public function repairWebhook(WebhookRegistrar $registrar): void
    {
        $status = $registrar->check($this->form);
        $this->form->refresh();
        session()->flash($status === WebhookStatus::Active ? 'status' : 'error', $status === WebhookStatus::Active ? 'Webhook is active.' : 'Webhook could not be repaired: '.($this->form->webhook_last_error ?? $status->label()));
    }

    public function resync(): void
    {
        BackfillFormResponses::dispatch($this->form);
        session()->flash('status', 'Re-sync started. Responses are imported in the background.');
    }

    public function rescore(): void
    {
        RescoreFormSubmissions::dispatch($this->form, null, $this->useLatestMapping);
        session()->flash('status', 'Recalculation started.');
    }

    public function unlink(FormLinker $linker): void
    {
        /** @var User $user */
        $user = auth()->user();
        $linker->unlink($this->form, $user);
        session()->flash('status', 'Form unlinked. Its evaluations stay visible on the dashboard.');
        $this->redirect(route('admin.hospitals.show', ['hospital' => $this->form->hospital, 'tab' => 'forms']), navigate: true);
    }

    public function render(): View
    {
        $this->form->load(['hospital', 'currentVersion', 'versions' => fn ($q) => $q->withCount('fields', 'submissions')]);

        return view('livewire.admin.forms.show', [
            'branchFields' => $this->form->currentVersion?->fields()->whereIn('type', ['dropdown', 'multiple_choice', 'short_text'])->orderBy('display_order')->get(['typeform_field_id', 'title', 'type']) ?? collect(),
            'submissionCount' => $this->form->submissions()->count(),
            'lastSubmission' => $this->form->submissions()->max('submitted_at'),
            'httpsOk' => str_starts_with((string) config('app.url'), 'https://'),
        ]);
    }
}
