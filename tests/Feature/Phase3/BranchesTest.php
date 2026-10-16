<?php

use App\Domain\Forms\FormVersioner;
use App\Domain\Typeform\WebhookSignature;
use App\Livewire\Admin\Forms\Show as FormShow;
use App\Livewire\Hospital\Dashboard;
use App\Livewire\Hospital\Evaluations\Index;
use App\Models\Branch;
use App\Models\Form;
use App\Models\FormField;
use App\Models\Hospital;
use App\Models\Submission;
use Livewire\Livewire;

beforeEach(function () {
    $this->hospital = Hospital::factory()->create();
    $this->form = Form::factory()->for($this->hospital)->create(['typeform_form_id' => 'rACXmOJ2']);
    $this->version = app(FormVersioner::class)->createInitialVersion($this->form, fixture('form_definition_rACXmOJ2.json'));
    $this->raw = (string) file_get_contents(__DIR__.'/../../Fixtures/typeform/webhook_payload_rACXmOJ2.json');
});

function postSigned($test, Form $form, string $raw): void
{
    $test->call('POST', '/webhooks/typeform/'.$form->uuid, [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_TYPEFORM_SIGNATURE' => WebhookSignature::sign($raw, $form->webhook_secret)], $raw)->assertOk();
}

test('branch from a hidden field is created on first sight and shown as a filter', function () {
    Livewire::actingAs(superAdmin())
        ->test(FormShow::class, ['form' => $this->form])
        ->set('branchSourceType', 'hidden')
        ->set('branchHiddenKey', 'branch')
        ->call('saveSettings')
        ->assertHasNoErrors();

    expect($this->form->fresh()->settings->branchSource)->toEqualCanonicalizing(['type' => 'hidden', 'key' => 'branch']);

    postSigned($this, $this->form->fresh(), $this->raw);

    $branch = Branch::query()->where('hospital_id', $this->hospital->id)->where('name', 'Main campus')->firstOrFail();
    $submission = Submission::query()->firstOrFail();
    expect($submission->branch_id)->toBe($branch->id);
    $submission->update(['submitted_at' => now()->subDay()]);

    $viewer = viewer($this->hospital);
    Livewire::actingAs($viewer)->test(Dashboard::class)
        ->assertSee('All branches')
        ->assertSee('1 evaluation')
        ->set('branch', (string) $branch->id)
        ->assertSee('1 evaluation')
        ->set('branch', (string) Branch::factory()->create(['hospital_id' => $this->hospital->id])->id)
        ->assertSee('No evaluations yet');

    Livewire::actingAs($viewer)->test(Index::class)->assertSee('Main campus');
});

test('branch from a question, and re-scoring applies a newly configured source to existing evaluations', function () {
    postSigned($this, $this->form, $this->raw);
    $submission = Submission::query()->firstOrFail();
    expect($submission->branch_id)->toBeNull();

    $location = FormField::query()->where('form_version_id', $this->version->id)->where('title', 'Location')->firstOrFail();
    Livewire::actingAs(superAdmin())
        ->test(FormShow::class, ['form' => $this->form])
        ->set('branchSourceType', 'field')
        ->set('branchFieldId', $location->typeform_field_id)
        ->call('saveSettings')
        ->assertHasNoErrors()
        ->call('rescore');

    expect($submission->fresh()->branch->name)->toBe('Lahore');
});

test('branch source validation', function () {
    Livewire::actingAs(superAdmin())
        ->test(FormShow::class, ['form' => $this->form])
        ->set('branchSourceType', 'field')
        ->set('branchFieldId', '')
        ->call('saveSettings')
        ->assertHasErrors(['branchFieldId']);
});
