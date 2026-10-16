<?php

use App\Domain\Forms\FormVersioner;
use App\Domain\Typeform\WebhookSignature;
use App\Models\Form;
use App\Models\Hospital;
use App\Models\Submission;

test('an evaluation can be downloaded as PDF by its hospital and by admins, but not by other hospitals', function () {
    $hospital = Hospital::factory()->create();
    $form = Form::factory()->for($hospital)->create(['typeform_form_id' => 'rACXmOJ2']);
    app(FormVersioner::class)->createInitialVersion($form, fixture('form_definition_rACXmOJ2.json'));
    $raw = (string) file_get_contents(__DIR__.'/../../Fixtures/typeform/webhook_payload_rACXmOJ2.json');
    $this->call('POST', '/webhooks/typeform/'.$form->uuid, [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_TYPEFORM_SIGNATURE' => WebhookSignature::sign($raw, $form->webhook_secret)], $raw)->assertOk();
    $submission = Submission::query()->firstOrFail();

    $response = $this->actingAs(viewer($hospital))->get(route('evaluations.pdf', $submission));
    $response->assertOk()->assertHeader('content-type', 'application/pdf');
    expect($response->headers->get('content-disposition'))->toContain('evaluation-')
        ->and(substr((string) $response->getContent(), 0, 4))->toBe('%PDF');

    $this->actingAs(superAdmin())->get(route('evaluations.pdf', $submission))->assertOk();
    $this->actingAs(viewer())->get(route('evaluations.pdf', $submission))->assertNotFound();

    // The detail page offers the download
    $this->actingAs(viewer($hospital))->get(route('evaluations.show', $submission))->assertSee('Download PDF');
})->group('slow');
