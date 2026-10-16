<?php

use App\Domain\Forms\FormVersioner;
use App\Domain\Forms\MappingEditor;
use App\Domain\Scoring\Rescorer;
use App\Domain\Typeform\WebhookSignature;
use App\Jobs\RescoreFormSubmissions;
use App\Models\Form;
use App\Models\FormField;
use App\Models\Submission;

beforeEach(function () {
    $this->form = Form::factory()->create(['typeform_form_id' => 'rACXmOJ2']);
    $this->v1 = app(FormVersioner::class)->createInitialVersion($this->form, fixture('form_definition_rACXmOJ2.json'));
    $raw = (string) file_get_contents(__DIR__.'/../../Fixtures/typeform/webhook_payload_rACXmOJ2.json');
    $this->call('POST', '/webhooks/typeform/'.$this->form->uuid, [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_TYPEFORM_SIGNATURE' => WebhookSignature::sign($raw, $this->form->webhook_secret),
    ], $raw)->assertOk();
    $this->submission = Submission::query()->firstOrFail();
});

test('the rescore command recalculates scores after a mapping change', function () {
    expect($this->submission->overall_pct)->toBe(77.65);

    // Admin: Waiting Area counts double, "Were visible safety hazards present?" should not be scored.
    $waiting = $this->v1->sections()->where('name', 'Waiting Area')->firstOrFail();
    $waiting->update(['weight' => 2.0]);
    FormField::query()->where('form_version_id', $this->v1->id)->where('title', 'Were visible safety hazards present?')->update(['is_scored' => false]);

    $this->artisan('evaluations:rescore', ['form_uuid' => $this->form->uuid])->assertSuccessful();

    $this->submission->refresh();
    // Facility & Cleanliness: 30/40 = 75 (hazard question dropped); overall = (85+75+60×2+92+80+77.5+75+91.67)/9 = 77.35
    expect((float) $this->submission->sectionScores()->where('section_name', 'Facility & Cleanliness')->value('pct'))->toBe(75.0)
        ->and($this->submission->overall_pct)->toBe(77.35)
        ->and($this->submission->answers()->whereHas('field', fn ($q) => $q->where('title', 'Were visible safety hazards present?'))->value('points'))->toBeNull();
    $this->assertDatabaseHas('audit_logs', ['action' => 'form.rescored', 'subject_id' => $this->form->id]);
});

test('rescoring with --latest moves evaluations to the current version', function () {
    $v2 = app(FormVersioner::class)->createVersionFrom($this->form, fixture('form_definition_rACXmOJ2_v2.json'), $this->v1);
    $this->form->forceFill(['current_version_id' => $v2->id])->save();
    $v2->sections()->where('name', 'Phone booking')->update(['weight' => 0]);

    $this->artisan('evaluations:rescore', ['form_uuid' => $this->form->uuid, '--latest' => true])->assertSuccessful();

    $this->submission->refresh();
    expect($this->submission->form_version_id)->toBe($v2->id)
        ->and($this->submission->overall_pct)->toBe(76.6); // Phone booking (85) excluded: (75+60+92+80+77.5+60+91.67)/7
});

test('rescoring without --latest keeps each evaluation on its own version', function () {
    $v2 = app(FormVersioner::class)->createVersionFrom($this->form, fixture('form_definition_rACXmOJ2_v2.json'), $this->v1);
    $this->form->forceFill(['current_version_id' => $v2->id])->save();

    (new RescoreFormSubmissions($this->form))->handle(app(Rescorer::class));

    expect($this->submission->fresh()->form_version_id)->toBe($this->v1->id);
});

test('the mapping editor save followed by rescore updates history', function () {
    $sections = $this->v1->sections()->get()->map(fn ($s) => [
        'id' => $s->id, 'name' => $s->name, 'weight' => $s->name === 'Overall' ? 0 : (float) $s->weight,
        'fields' => $s->fields()->pluck('id')->all(),
    ])->all();

    app(MappingEditor::class)->save($this->v1, $sections, [], superAdmin());
    (new RescoreFormSubmissions($this->form, $this->v1))->handle(app(Rescorer::class));

    // Overall section excluded: (85+75+60+92+80+77.5+60)/7 = 75.64
    expect($this->submission->fresh()->overall_pct)->toBe(75.64);
});

test('unknown form uuid fails the command', function () {
    $this->artisan('evaluations:rescore', ['form_uuid' => 'nope'])->assertFailed();
});
