<?php

use App\Domain\Forms\FormVersioner;
use App\Enums\FormStatus;
use App\Enums\WebhookStatus;
use App\Jobs\BackfillFormResponses;
use App\Jobs\RescoreFormSubmissions;
use App\Livewire\Admin\Forms\Show;
use App\Models\Form;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    Queue::fake();
    $this->admin = superAdmin();
    $this->form = Form::factory()->create(['typeform_form_id' => 'F1', 'webhook_status' => WebhookStatus::Missing]);
    app(FormVersioner::class)->createInitialVersion($this->form, fixture('form_rating_yesno.json'));
});

test('scoring settings are saved and audited', function () {
    Livewire::actingAs($this->admin)
        ->test(Show::class, ['form' => $this->form])
        ->set('rag_green_min', 85)
        ->set('rag_amber_min', 65)
        ->set('yes_no_points', 5)
        ->set('include_partial', true)
        ->call('saveSettings')
        ->assertHasNoErrors();

    $settings = $this->form->fresh()->settings;
    expect($settings->ragGreenMin)->toBe(85.0)->and($settings->ragAmberMin)->toBe(65.0)->and($settings->yesNoPoints)->toBe(5.0)->and($settings->includePartial)->toBeTrue();
    $this->assertDatabaseHas('audit_logs', ['action' => 'form.settings_updated', 'subject_id' => $this->form->id]);
});

test('green threshold must not be below amber', function () {
    Livewire::actingAs($this->admin)
        ->test(Show::class, ['form' => $this->form])
        ->set('rag_green_min', 50)
        ->set('rag_amber_min', 60)
        ->call('saveSettings')
        ->assertHasErrors(['rag_green_min']);
});

test('repair webhook re-registers a missing webhook', function () {
    Http::fake(['*/forms/F1/webhooks/*' => Http::sequence()->push(null, 404)->push(['enabled' => true])]);

    Livewire::actingAs($this->admin)
        ->test(Show::class, ['form' => $this->form])
        ->call('repairWebhook');

    expect($this->form->fresh()->webhook_status)->toBe(WebhookStatus::Active);
});

test('re-sync and recalculate dispatch their jobs', function () {
    Livewire::actingAs($this->admin)
        ->test(Show::class, ['form' => $this->form])
        ->call('resync')
        ->set('useLatestMapping', true)
        ->call('rescore');

    Queue::assertPushed(BackfillFormResponses::class);
    Queue::assertPushed(RescoreFormSubmissions::class, fn (RescoreFormSubmissions $job) => $job->useLatestMapping === true && $job->version === null);
});

test('unlink from the form page', function () {
    Http::fake(['*/forms/F1/webhooks/*' => Http::response(null, 204)]);

    Livewire::actingAs($this->admin)
        ->test(Show::class, ['form' => $this->form])
        ->call('unlink')
        ->assertRedirect();

    expect($this->form->fresh()->status)->toBe(FormStatus::Unlinked);
});
