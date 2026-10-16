<?php

use App\Domain\Forms\FormVersioner;
use App\Domain\Typeform\WebhookSignature;
use App\Livewire\Hospital\Dashboard;
use App\Livewire\Hospital\Evaluations\Index;
use App\Models\Form;
use App\Models\Hospital;
use App\Models\Submission;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

/**
 * Ittefaq users can never see CMH data: list, detail (404), dashboard numbers, CSV export, file proxy.
 */
beforeEach(function () {
    $this->ittefaq = Hospital::factory()->create(['name' => 'Ittefaq Hospital']);
    $this->cmh = Hospital::factory()->create(['name' => 'CMH']);

    foreach (['ittefaq' => 'rACXmOJ2', 'cmh' => 'cmhFORM1'] as $key => $typeformId) {
        $hospital = $this->{$key};
        $form = Form::factory()->for($hospital)->create(['typeform_form_id' => $typeformId, 'title' => strtoupper($key).' survey']);
        app(FormVersioner::class)->createInitialVersion($form, fixture('form_definition_rACXmOJ2.json'));
        $raw = (string) file_get_contents(__DIR__.'/../../Fixtures/typeform/webhook_payload_rACXmOJ2.json');
        $raw = str_replace('Ittefaq Hospital', $key === 'cmh' ? 'CMH Secret Facility' : 'Ittefaq Hospital', $raw);
        $this->call('POST', '/webhooks/typeform/'.$form->uuid, [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_TYPEFORM_SIGNATURE' => WebhookSignature::sign($raw, $form->webhook_secret)], $raw)->assertOk();
        $this->{$key.'Form'} = $form;
        $this->{$key.'Submission'} = Submission::query()->where('form_id', $form->id)->firstOrFail();
        $this->{$key.'Submission'}->update(['submitted_at' => now()->subDays(2)]);
    }

    $this->ittefaqUser = viewer($this->ittefaq);
});

test('list never shows the other hospital', function () {
    Livewire::actingAs($this->ittefaqUser)
        ->test(Index::class)
        ->assertSee('Ittefaq Hospital')
        ->assertDontSee('CMH Secret Facility')
        ->assertDontSee('CMH survey');

    // even when trying to filter by the other hospital's form uuid
    Livewire::actingAs($this->ittefaqUser)
        ->test(Index::class)
        ->set('form', $this->cmhForm->uuid)
        ->assertDontSee('CMH Secret Facility');
});

test('detail of another hospital evaluation is 404, not 403', function () {
    $this->actingAs($this->ittefaqUser)->get(route('evaluations.show', $this->cmhSubmission))->assertNotFound();
    $this->actingAs($this->ittefaqUser)->get(route('evaluations.show', $this->ittefaqSubmission))->assertOk();
    $this->actingAs(superAdmin())->get(route('evaluations.show', $this->cmhSubmission))->assertOk();
});

test('dashboard numbers only count the own hospital', function () {
    Livewire::actingAs($this->ittefaqUser)
        ->test(Dashboard::class)
        ->assertSee('1 evaluation')
        ->assertDontSee('2 evaluations')
        ->assertDontSee('CMH');
});

test('CSV export only contains the own hospital, even with a foreign form filter', function () {
    $csv = $this->actingAs($this->ittefaqUser)->get(route('evaluations.export'))->streamedContent();
    expect($csv)->toContain('Ittefaq Hospital')->not->toContain('CMH Secret Facility');

    $this->actingAs($this->ittefaqUser)->get(route('evaluations.export', ['form' => $this->cmhForm->uuid]))->assertNotFound();
    $this->actingAs($this->ittefaqUser)->get(route('evaluations.export', ['hospital' => $this->cmh->uuid]))->assertOk()
        ->streamedContent();
    expect($this->actingAs($this->ittefaqUser)->get(route('evaluations.export', ['hospital' => $this->cmh->uuid]))->streamedContent())->not->toContain('CMH Secret Facility');
});

test('file proxy refuses files of another hospital', function () {
    Http::fake();
    $answer = $this->cmhSubmission->answers()->where('answer_type', 'file_url')->firstOrFail();

    $this->actingAs($this->ittefaqUser)->get(route('evaluations.files.show', [$this->cmhSubmission, $answer->id]))->assertNotFound();
    Http::assertNothingSent();
});

test('pending-review and rejected evaluations are hidden from hospital users but visible to admins', function () {
    $this->ittefaqSubmission->update(['status' => 'pending_review']);

    $this->actingAs($this->ittefaqUser)->get(route('evaluations.show', $this->ittefaqSubmission))->assertNotFound();
    Livewire::actingAs($this->ittefaqUser)->test(Index::class)->assertSee('No evaluations match');
    $this->actingAs(superAdmin())->get(route('evaluations.show', $this->ittefaqSubmission))->assertOk();
});

test('partial evaluations are hidden unless the form includes them', function () {
    $this->ittefaqSubmission->update(['is_partial' => true]);
    $this->actingAs($this->ittefaqUser)->get(route('evaluations.show', $this->ittefaqSubmission))->assertNotFound();

    $this->ittefaqForm->settings = $this->ittefaqForm->settings->with(['include_partial' => true]);
    $this->ittefaqForm->save();
    $this->actingAs($this->ittefaqUser)->get(route('evaluations.show', $this->ittefaqSubmission))->assertOk();
});

test('the admin hospital evaluations route is admin-only', function () {
    $this->actingAs($this->ittefaqUser)->get(route('hospitals.evaluations.index', $this->ittefaq))->assertForbidden();
    $this->actingAs(superAdmin())->get(route('hospitals.evaluations.index', $this->cmh))->assertOk()->assertSee('CMH Secret Facility');
});
