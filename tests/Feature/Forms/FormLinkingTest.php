<?php

use App\Domain\Forms\FormLinker;
use App\Domain\Forms\WebhookRegistrar;
use App\Enums\FormStatus;
use App\Enums\WebhookStatus;
use App\Jobs\BackfillFormResponses;
use App\Models\AuditLog;
use App\Models\Form;
use App\Models\Hospital;
use App\Models\TypeformConnection;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Queue::fake();
    $this->connection = TypeformConnection::factory()->create();
    $this->hospital = Hospital::factory()->create(['name' => 'Ittefaq Hospital']);
    $this->admin = superAdmin();
});

test('linking a form creates the version, registers the webhook, dispatches backfill and audits', function () {
    Http::fake([
        '*/forms/rACXmOJ2' => Http::response(fixture('form_definition_rACXmOJ2.json')),
        '*/forms/rACXmOJ2/webhooks/*' => Http::response(['id' => 'wh1', 'tag' => 'x', 'url' => 'u', 'enabled' => true]),
    ]);

    $form = app(FormLinker::class)->link($this->hospital, $this->connection, 'rACXmOJ2', $this->admin);

    expect($form->status)->toBe(FormStatus::Active)
        ->and($form->webhook_status)->toBe(WebhookStatus::Active)
        ->and($form->webhook_tag)->toBe('emotion-'.substr($form->uuid, 0, 8))
        ->and(strlen($form->webhook_secret))->toBeGreaterThanOrEqual(40)
        ->and($form->currentVersion->version_number)->toBe(1)
        ->and($form->currentVersion->needs_review)->toBeTrue()
        ->and($form->currentVersion->sections()->count())->toBe(10)
        ->and($form->title)->toBe('Hospital Mystery Shopping Evaluation')
        ->and($form->linked_by)->toBe($this->admin->id);

    $expectedUrl = 'https://emotion.test/webhooks/typeform/'.$form->uuid;
    Http::assertSent(fn (Request $r) => $r->method() === 'PUT'
        && $r->url() === 'https://api.typeform.com/forms/rACXmOJ2/webhooks/'.$form->webhook_tag
        && $r['url'] === $expectedUrl
        && $r['secret'] === $form->webhook_secret
        && $r['enabled'] === true);

    Queue::assertPushed(BackfillFormResponses::class, fn (BackfillFormResponses $job) => $job->form->is($form));
    $this->assertDatabaseHas('audit_logs', ['action' => 'form.linked', 'subject_id' => $form->id, 'hospital_id' => $this->hospital->id, 'user_id' => $this->admin->id]);
});

test('webhook registration failure still links the form with webhook_status=error', function () {
    Http::fake([
        '*/forms/rACXmOJ2' => Http::response(fixture('form_definition_rACXmOJ2.json')),
        '*/forms/rACXmOJ2/webhooks/*' => Http::response(['code' => 'FORBIDDEN'], 403),
    ]);

    $form = app(FormLinker::class)->link($this->hospital, $this->connection, 'rACXmOJ2', $this->admin);

    expect($form->status)->toBe(FormStatus::Active)
        ->and($form->webhook_status)->toBe(WebhookStatus::Error)
        ->and($form->webhook_last_error)->toContain('403');
    Queue::assertPushed(BackfillFormResponses::class);
});

test('a form linked to another hospital cannot be linked again or moved', function () {
    $other = Hospital::factory()->create();
    Form::factory()->for($other)->create(['typeform_connection_id' => $this->connection->id, 'typeform_form_id' => 'TAKEN']);
    Form::factory()->for($other)->unlinked()->create(['typeform_connection_id' => $this->connection->id, 'typeform_form_id' => 'MOVED']);

    expect(fn () => app(FormLinker::class)->link($this->hospital, $this->connection, 'TAKEN', $this->admin))->toThrow(ValidationException::class);
    expect(fn () => app(FormLinker::class)->link($this->hospital, $this->connection, 'MOVED', $this->admin))->toThrow(ValidationException::class);
    Http::assertNothingSent();
});

test('unlinking deletes the webhook, keeps the form and its data, and re-linking re-registers it', function () {
    Http::fake([
        '*/forms/rACXmOJ2' => Http::response(fixture('form_definition_rACXmOJ2.json')),
        '*/forms/rACXmOJ2/webhooks/*' => Http::sequence()
            ->push(['id' => 'wh1', 'enabled' => true])     // PUT on link
            ->push(null, 404)                                // DELETE on unlink (ignored)
            ->push(['id' => 'wh2', 'enabled' => true]),      // PUT on relink
    ]);
    $linker = app(FormLinker::class);
    $form = $linker->link($this->hospital, $this->connection, 'rACXmOJ2', $this->admin);

    $linker->unlink($form, $this->admin);
    $form->refresh();
    expect($form->status)->toBe(FormStatus::Unlinked)
        ->and($form->webhook_status)->toBe(WebhookStatus::Unregistered)
        ->and(Form::query()->count())->toBe(1);
    Http::assertSent(fn (Request $r) => $r->method() === 'DELETE');
    $this->assertDatabaseHas('audit_logs', ['action' => 'form.unlinked', 'subject_id' => $form->id]);

    $relinked = $linker->link($this->hospital, $this->connection, 'rACXmOJ2', $this->admin);
    expect($relinked->id)->toBe($form->id)
        ->and($relinked->status)->toBe(FormStatus::Active)
        ->and($relinked->webhook_status)->toBe(WebhookStatus::Active)
        ->and($relinked->versions()->count())->toBe(1);
    // BackfillFormResponses is a unique job; the fake holds the lock from the first dispatch, so assert at least once.
    Queue::assertPushed(BackfillFormResponses::class);
});

test('webhook check repairs missing, disabled or wrong-url webhooks', function () {
    $form = Form::factory()->for($this->hospital)->create(['typeform_connection_id' => $this->connection->id, 'typeform_form_id' => 'F1']);
    $registrar = app(WebhookRegistrar::class);

    Http::fake(['*/forms/F1/webhooks/*' => Http::sequence()
        ->push(null, 404)                                                       // 1. GET missing
        ->push(['enabled' => true])                                             // 1. PUT repair
        ->push(['enabled' => true, 'url' => 'https://old.example/hook'])        // 2. GET wrong url
        ->push(['enabled' => true])                                             // 2. PUT repair
        ->push(['enabled' => false, 'url' => $form->webhookUrl()])              // 3. GET disabled
        ->push(['enabled' => true])                                             // 3. PUT repair
        ->push(['enabled' => true, 'url' => $form->webhookUrl()])              // 4. GET healthy
        ->whenEmpty(Http::response('down', 500)),                               // 5. API failure (retried)
    ]);

    expect($registrar->check($form))->toBe(WebhookStatus::Active);
    expect(AuditLog::query()->where('action', 'webhook.repaired')->where('subject_id', $form->id)->where('changes->reason', 'missing')->exists())->toBeTrue();
    Http::assertSentCount(2);

    expect($registrar->check($form->fresh()))->toBe(WebhookStatus::Active);
    Http::assertSentCount(4);

    expect($registrar->check($form->fresh()))->toBe(WebhookStatus::Active);
    Http::assertSentCount(6);

    expect($registrar->check($form->fresh()))->toBe(WebhookStatus::Active);
    Http::assertSentCount(7); // healthy: no PUT

    expect($registrar->check($form->fresh()))->toBe(WebhookStatus::Error);
    expect($form->fresh()->webhook_last_error)->toContain('500');
});
