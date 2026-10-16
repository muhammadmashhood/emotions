<?php

namespace App\Domain\Forms;

use App\Domain\Audit\AuditLogger;
use App\Domain\Typeform\TypeformClient;
use App\Enums\FormStatus;
use App\Enums\WebhookStatus;
use App\Jobs\BackfillFormResponses;
use App\Models\Form;
use App\Models\Hospital;
use App\Models\TypeformConnection;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Link / unlink orchestration (spec 5.4 / 5.5).
 */
class FormLinker
{
    public function __construct(
        private readonly FormVersioner $versioner,
        private readonly WebhookRegistrar $webhooks,
        private readonly AuditLogger $audit,
    ) {}

    public function link(Hospital $hospital, TypeformConnection $connection, string $typeformFormId, ?User $by): Form
    {
        $existing = Form::query()->where('typeform_form_id', $typeformFormId)->first();

        if ($existing !== null) {
            if ($existing->isActive()) {
                throw ValidationException::withMessages(['form' => 'This Typeform form is already linked to '.$existing->hospital->name.'.']);
            }
            if ($existing->hospital_id !== $hospital->id) {
                throw ValidationException::withMessages(['form' => 'This form was previously linked to another hospital and cannot be moved.']);
            }

            return $this->relink($existing, $by);
        }

        $client = TypeformClient::for($connection);
        $definition = $client->form($typeformFormId);

        $form = DB::transaction(function () use ($hospital, $connection, $typeformFormId, $definition, $by): Form {
            $form = Form::query()->create([
                'hospital_id' => $hospital->id,
                'typeform_connection_id' => $connection->id,
                'typeform_form_id' => $typeformFormId,
                'title' => (string) ($definition['title'] ?? $typeformFormId),
                'webhook_status' => WebhookStatus::Unregistered,
                'status' => FormStatus::Active,
                'linked_by' => $by?->id,
            ]);

            $this->versioner->createInitialVersion($form, $definition);

            return $form;
        });

        $this->webhooks->register($form, $client);

        BackfillFormResponses::dispatch($form);

        $this->audit->log('form.linked', $form, [
            'typeform_form_id' => $typeformFormId,
            'title' => $form->title,
            'webhook_status' => $form->webhook_status->value,
            'automatic' => $by === null,
        ], $hospital, $by);

        return $form->refresh();
    }

    /**
     * Re-link a form previously unlinked from the same hospital: re-register the webhook, refresh structure, reconcile.
     */
    public function relink(Form $form, ?User $by): Form
    {
        $form->forceFill(['status' => FormStatus::Active, 'linked_by' => $by?->id])->save();

        $client = TypeformClient::for($form->connection);
        $this->versioner->refreshFromTypeform($form, $client);
        $this->webhooks->register($form, $client);

        BackfillFormResponses::dispatch($form);

        $this->audit->log('form.relinked', $form, ['typeform_form_id' => $form->typeform_form_id], $form->hospital_id, $by);

        return $form->refresh();
    }

    public function unlink(Form $form, User $by): void
    {
        $this->webhooks->unregister($form);
        $form->forceFill(['status' => FormStatus::Unlinked, 'webhook_status' => WebhookStatus::Unregistered])->save();

        $this->audit->log('form.unlinked', $form, ['typeform_form_id' => $form->typeform_form_id], $form->hospital_id, $by);
    }
}
