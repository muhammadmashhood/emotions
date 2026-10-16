<?php

namespace App\Domain\Forms;

use App\Domain\Audit\AuditLogger;
use App\Domain\Typeform\Exceptions\TypeformException;
use App\Domain\Typeform\Exceptions\TypeformNotFoundException;
use App\Domain\Typeform\TypeformClient;
use App\Enums\WebhookStatus;
use App\Models\Form;

/**
 * Registers, checks and repairs the Typeform webhook of a form. Nobody copies URLs by hand.
 */
class WebhookRegistrar
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * PUT the webhook. Returns true on success; on failure records the error on the form and returns false.
     */
    public function register(Form $form, ?TypeformClient $client = null): bool
    {
        $client ??= TypeformClient::for($form->connection);

        try {
            $info = $client->putWebhook($form->typeform_form_id, $form->webhook_tag, $form->webhookUrl(), $form->webhook_secret);

            $form->forceFill([
                'webhook_status' => $info->enabled ? WebhookStatus::Active : WebhookStatus::Disabled,
                'webhook_checked_at' => now(),
                'webhook_last_error' => null,
            ])->save();

            return true;
        } catch (TypeformException $e) {
            $form->forceFill([
                'webhook_status' => WebhookStatus::Error,
                'webhook_checked_at' => now(),
                'webhook_last_error' => $e->getMessage(),
            ])->save();

            return false;
        }
    }

    /**
     * GET the webhook and repair it when missing, disabled or pointing elsewhere.
     */
    public function check(Form $form, ?TypeformClient $client = null): WebhookStatus
    {
        $client ??= TypeformClient::for($form->connection);

        try {
            $info = $client->getWebhook($form->typeform_form_id, $form->webhook_tag);
            $healthy = $info->enabled && $info->url === $form->webhookUrl();

            if ($healthy) {
                $form->forceFill(['webhook_status' => WebhookStatus::Active, 'webhook_checked_at' => now(), 'webhook_last_error' => null])->save();

                return WebhookStatus::Active;
            }

            $form->forceFill(['webhook_status' => $info->enabled ? WebhookStatus::Error : WebhookStatus::Disabled])->save();
            $reason = $info->enabled ? 'wrong_url' : 'disabled';
        } catch (TypeformNotFoundException) {
            $form->forceFill(['webhook_status' => WebhookStatus::Missing])->save();
            $reason = 'missing';
        } catch (TypeformException $e) {
            $form->forceFill(['webhook_status' => WebhookStatus::Error, 'webhook_checked_at' => now(), 'webhook_last_error' => $e->getMessage()])->save();

            return WebhookStatus::Error;
        }

        if ($this->register($form, $client)) {
            $this->audit->log('webhook.repaired', $form, ['reason' => $reason], $form->hospital_id);
        }

        return $form->webhook_status;
    }

    public function unregister(Form $form, ?TypeformClient $client = null): void
    {
        $client ??= TypeformClient::for($form->connection);

        try {
            $client->deleteWebhook($form->typeform_form_id, $form->webhook_tag);
            $form->forceFill(['webhook_status' => WebhookStatus::Unregistered, 'webhook_last_error' => null, 'webhook_checked_at' => now()])->save();
        } catch (TypeformException $e) {
            $form->forceFill(['webhook_status' => WebhookStatus::Unregistered, 'webhook_last_error' => $e->getMessage(), 'webhook_checked_at' => now()])->save();
        }
    }
}
