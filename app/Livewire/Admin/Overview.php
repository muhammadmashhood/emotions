<?php

namespace App\Livewire\Admin;

use App\Domain\Forms\WebhookRegistrar;
use App\Domain\Typeform\ConnectionManager;
use App\Enums\SubmissionStatus;
use App\Enums\WebhookStatus;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Form;
use App\Models\Hospital;
use App\Models\Submission;
use App\Models\WebhookDelivery;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin overview (spec 9.4, eMotion Admin design): headline KPIs, everything that
 * needs attention, and recent activity from the audit log.
 */
#[Layout('layouts.app')]
class Overview extends Component
{
    public function repairWebhook(string $formUuid, WebhookRegistrar $registrar): void
    {
        $form = Form::query()->active()->where('uuid', $formUuid)->firstOrFail();
        $status = $registrar->check($form);

        session()->flash(
            $status === WebhookStatus::Active ? 'status' : 'error',
            $status === WebhookStatus::Active ? "Webhook for {$form->title} is active again." : "Webhook for {$form->title} could not be repaired: ".($form->fresh()->webhook_last_error ?? $status->label()),
        );
    }

    public function render(ConnectionManager $connections): View
    {
        $pending = Submission::query()->where('status', SubmissionStatus::PendingReview->value);
        $pendingCount = (clone $pending)->count();
        $oldestPending = (clone $pending)->min('submitted_at');

        $reviewForms = Form::query()->active()->with(['hospital:id,name,uuid', 'currentVersion'])
            ->whereHas('currentVersion', fn ($v) => $v->where('needs_review', true))->orderBy('title')->get();
        $webhookForms = Form::query()->active()->with('hospital:id,name,uuid')
            ->where('webhook_status', '!=', WebhookStatus::Active->value)->orderBy('title')->get();
        $failedDeliveries = WebhookDelivery::query()->with('form:id,title,uuid,hospital_id', 'form.hospital:id,name')
            ->where('received_at', '>=', now()->subDay())
            ->where('signature_valid', true)->whereNotNull('error')->where('error', 'not like', 'Duplicate%')
            ->orderByDesc('received_at')->limit(20)->get();
        $invalidDeliveries = WebhookDelivery::query()->where('received_at', '>=', now()->subDay())->where('signature_valid', false)->count();
        $unlinkedHospitals = Hospital::query()->where('is_active', true)
            ->whereDoesntHave('forms', fn ($q) => $q->active())->orderBy('name')->get();
        $connection = $connections->current();

        $hospitalCount = Hospital::query()->count();
        $activeHospitals = Hospital::query()->where('is_active', true)->count();
        $activeForms = Form::query()->active()->count();

        return view('livewire.admin.overview', [
            'kpis' => [
                'hospitals' => $activeHospitals,
                'hospitalTotal' => $hospitalCount,
                'facilities' => Branch::query()->where('is_active', true)->count(),
                'facilityHospitals' => Branch::query()->where('is_active', true)->distinct()->count('hospital_id'),
                'pending' => $pendingCount,
                'oldestPending' => $oldestPending ? Carbon::parse($oldestPending) : null,
                'webhooksFailing' => $webhookForms->count(),
                'activeForms' => $activeForms,
            ],
            'pendingCount' => $pendingCount,
            'reviewForms' => $reviewForms,
            'webhookForms' => $webhookForms,
            'failedDeliveries' => $failedDeliveries,
            'invalidDeliveries' => $invalidDeliveries,
            'unlinkedHospitals' => $unlinkedHospitals,
            'connection' => $connection,
            'alertCount' => ($pendingCount > 0 ? 1 : 0) + $reviewForms->count() + $webhookForms->count() + $failedDeliveries->count()
                + $unlinkedHospitals->count() + ($connection === null || $connection->last_error ? 1 : 0),
            'activity' => AuditLog::query()->with(['user:id,name', 'hospital:id,name'])
                // Staff sign-ins are noise here; hospital users signing in is useful to see.
                ->where(fn ($q) => $q->where('action', 'not like', 'auth.%')->orWhere(fn ($q) => $q->where('action', 'auth.login')->whereNotNull('hospital_id')))
                ->orderByDesc('created_at')->orderByDesc('id')->limit(6)->get(),
        ]);
    }
}
