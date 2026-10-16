<?php

namespace App\Livewire\Admin;

use App\Domain\Dashboard\DashboardFilters;
use App\Domain\Dashboard\DashboardQuery;
use App\Domain\Forms\WebhookRegistrar;
use App\Domain\Typeform\ConnectionManager;
use App\Enums\WebhookStatus;
use App\Models\Form;
use App\Models\Hospital;
use App\Models\User;
use App\Models\WebhookDelivery;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Admin overview (spec 9.4): per-hospital table plus an alerts panel.
 */
#[Layout('layouts.app')]
class Overview extends Component
{
    #[Url(except: '90d')]
    public string $period = '90d';

    public function setPeriod(string $period): void
    {
        $this->period = in_array($period, ['30d', '90d', '12m'], true) ? $period : '90d';
    }

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
        /** @var User $user */
        $user = auth()->user();
        $filters = DashboardFilters::fromRequest($this->period);
        $query = new DashboardQuery($user);
        $perHospital = $query->perHospital($filters);

        $hospitals = Hospital::query()
            ->withCount([
                'forms as active_forms_count' => fn ($q) => $q->active(),
                'forms as review_forms_count' => fn ($q) => $q->active()->whereHas('currentVersion', fn ($v) => $v->where('needs_review', true)),
                'forms as webhook_problem_count' => fn ($q) => $q->active()->where('webhook_status', '!=', WebhookStatus::Active->value),
            ])
            ->orderBy('name')
            ->get()
            ->map(function (Hospital $hospital) use ($perHospital) {
                $stats = $perHospital[$hospital->id] ?? ['count' => 0, 'avg' => null, 'previous_avg' => null];
                $delta = $stats['avg'] !== null && $stats['previous_avg'] !== null ? round($stats['avg'] - $stats['previous_avg'], 1) : null;

                return ['hospital' => $hospital, 'stats' => $stats, 'delta' => $delta];
            });

        $reviewForms = Form::query()->active()->with('hospital:id,name,uuid')
            ->whereHas('currentVersion', fn ($v) => $v->where('needs_review', true))->orderBy('title')->get();
        $webhookForms = Form::query()->active()->with('hospital:id,name,uuid')
            ->where('webhook_status', '!=', WebhookStatus::Active->value)->orderBy('title')->get();
        $failedDeliveries = WebhookDelivery::query()->with('form:id,title,uuid')
            ->where('received_at', '>=', now()->subDay())
            ->where('signature_valid', true)->whereNotNull('error')->where('error', 'not like', 'Duplicate%')
            ->orderByDesc('received_at')->limit(20)->get();
        $invalidDeliveries = WebhookDelivery::query()->where('received_at', '>=', now()->subDay())->where('signature_valid', false)->count();
        $connection = $connections->current();

        return view('livewire.admin.overview', [
            'filters' => $filters,
            'rows' => $hospitals,
            'reviewForms' => $reviewForms,
            'webhookForms' => $webhookForms,
            'failedDeliveries' => $failedDeliveries,
            'invalidDeliveries' => $invalidDeliveries,
            'connection' => $connection,
            'alertCount' => $reviewForms->count() + $webhookForms->count() + $failedDeliveries->count() + ($connection === null || $connection->last_error ? 1 : 0),
        ]);
    }
}
