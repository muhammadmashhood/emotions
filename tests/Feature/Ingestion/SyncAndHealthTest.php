<?php

use App\Domain\Forms\FormVersioner;
use App\Domain\Forms\WebhookRegistrar;
use App\Domain\Submissions\ResponseSyncer;
use App\Enums\SubmissionSource;
use App\Enums\WebhookStatus;
use App\Jobs\CheckFormStructure;
use App\Jobs\CheckFormWebhook;
use App\Jobs\ReconcileFormResponses;
use App\Models\Form;
use App\Models\Submission;
use App\Models\WebhookDelivery;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->form = Form::factory()->create(['typeform_form_id' => 'rACXmOJ2']);
    $this->v1 = app(FormVersioner::class)->createInitialVersion($this->form, fixture('form_definition_rACXmOJ2.json'));
});

function responseItem(string $token, string $submittedAt = '2026-02-01T09:20:00Z'): array
{
    $item = fixture('responses_page.json')['items'][0];
    $item['token'] = $token;
    $item['submitted_at'] = $submittedAt;

    return $item;
}

test('backfill pages with before tokens and ingests everything with source=backfill', function () {
    $pageOne = ['total_items' => 1001, 'page_count' => 2, 'items' => array_map(fn ($i) => responseItem("tok-$i"), range(1, 1000))];
    $pageTwo = ['total_items' => 1001, 'page_count' => 2, 'items' => [responseItem('tok-last')]];

    Http::fake(['*/forms/rACXmOJ2/responses*' => Http::sequence()->push($pageOne)->push($pageTwo)]);

    $result = app(ResponseSyncer::class)->backfill($this->form);

    expect($result)->toBe(['fetched' => 1001, 'created' => 1001])
        ->and(Submission::query()->count())->toBe(1001)
        ->and(Submission::query()->where('source', 'backfill')->count())->toBe(1001)
        ->and($this->form->fresh()->last_synced_at)->not->toBeNull();

    Http::assertSentCount(2);
    Http::assertSent(function (Request $r) {
        parse_str((string) parse_url($r->url(), PHP_URL_QUERY), $q);

        return ! isset($q['before']) && $q['completed'] === 'true' && $q['page_size'] === '1000';
    });
    Http::assertSent(function (Request $r) {
        parse_str((string) parse_url($r->url(), PHP_URL_QUERY), $q);

        return ($q['before'] ?? null) === 'tok-1000';
    });
})->group('slow');

test('reconcile fetches since last_synced_at minus 24h and inserts only missing responses', function () {
    $this->form->forceFill(['last_synced_at' => '2026-03-10 12:00:00'])->save();
    Submission::factory()->create(['form_id' => $this->form->id, 'form_version_id' => $this->v1->id, 'hospital_id' => $this->form->hospital_id, 'response_token' => 'existing']);

    Http::fake(['*/forms/rACXmOJ2/responses*' => Http::response(['total_items' => 2, 'page_count' => 1, 'items' => [responseItem('existing'), responseItem('missing')]])]);

    $result = app(ResponseSyncer::class)->reconcile($this->form);

    expect($result)->toBe(['fetched' => 2, 'created' => 1])
        ->and(Submission::query()->count())->toBe(2)
        ->and(Submission::query()->where('response_token', 'missing')->value('source'))->toBe(SubmissionSource::Reconcile);

    Http::assertSent(function (Request $r) {
        parse_str((string) parse_url($r->url(), PHP_URL_QUERY), $q);

        return $q['since'] === '2026-03-09T12:00:00Z';
    });
});

test('the health command dispatches jobs for active forms only', function () {
    Queue::fake();
    Form::factory()->unlinked()->create();

    $this->artisan('forms:health webhooks')->assertSuccessful();
    $this->artisan('forms:health structure')->assertSuccessful();
    $this->artisan('forms:health reconcile')->assertSuccessful();

    Queue::assertPushed(CheckFormWebhook::class, 1);
    Queue::assertPushed(CheckFormStructure::class, 1);
    Queue::assertPushed(ReconcileFormResponses::class, 1);
});

test('the webhook health job re-registers a missing webhook', function () {
    Http::fake(['*/webhooks/*' => Http::sequence()->push(null, 404)->push(['enabled' => true])]);
    $this->form->update(['webhook_status' => WebhookStatus::Active]);

    (new CheckFormWebhook($this->form))->handle(app(WebhookRegistrar::class));

    expect($this->form->fresh()->webhook_status)->toBe(WebhookStatus::Active);
    Http::assertSent(fn (Request $r) => $r->method() === 'PUT');
    $this->assertDatabaseHas('audit_logs', ['action' => 'webhook.repaired', 'subject_id' => $this->form->id]);
});

test('the structure job creates a new version only when the hash changes', function () {
    Http::fake(['*/forms/rACXmOJ2' => Http::sequence()
        ->push(fixture('form_definition_rACXmOJ2.json'))
        ->push(fixture('form_definition_rACXmOJ2_v2.json'))]);

    (new CheckFormStructure($this->form))->handle(app(FormVersioner::class));
    expect($this->form->versions()->count())->toBe(1);

    (new CheckFormStructure($this->form->fresh()))->handle(app(FormVersioner::class));
    expect($this->form->versions()->count())->toBe(2)
        ->and($this->form->fresh()->currentVersion->version_number)->toBe(2);
});

test('deliveries older than the retention period are pruned', function () {
    WebhookDelivery::factory()->create(['form_id' => $this->form->id, 'received_at' => now()->subDays(91)]);
    WebhookDelivery::factory()->create(['form_id' => $this->form->id, 'received_at' => now()->subDays(10)]);

    $this->artisan('deliveries:prune')->assertSuccessful();

    expect(WebhookDelivery::query()->count())->toBe(1);
});

test('schedule registers the health checks', function () {
    $events = collect(app(Schedule::class)->events())->map(fn ($e) => $e->command)->implode(' ');

    expect($events)->toContain('forms:health reconcile')->toContain('forms:health structure')->toContain('forms:health webhooks')->toContain('deliveries:prune');
});
