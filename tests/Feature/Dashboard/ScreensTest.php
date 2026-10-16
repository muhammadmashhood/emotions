<?php

use App\Domain\Forms\FormVersioner;
use App\Domain\Typeform\WebhookSignature;
use App\Livewire\Hospital\Dashboard;
use App\Livewire\Hospital\Evaluations\Index;
use App\Livewire\Hospital\Evaluations\Show;
use App\Models\Form;
use App\Models\Hospital;
use App\Models\Submission;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    $this->hospital = Hospital::factory()->create(['name' => 'Ittefaq Hospital']);
    $this->form = Form::factory()->for($this->hospital)->create(['typeform_form_id' => 'rACXmOJ2', 'title' => 'OPD survey']);
    app(FormVersioner::class)->createInitialVersion($this->form, fixture('form_definition_rACXmOJ2.json'));
    $raw = (string) file_get_contents(__DIR__.'/../../Fixtures/typeform/webhook_payload_rACXmOJ2.json');
    $this->call('POST', '/webhooks/typeform/'.$this->form->uuid, [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_TYPEFORM_SIGNATURE' => WebhookSignature::sign($raw, $this->form->webhook_secret)], $raw)->assertOk();
    $this->submission = Submission::query()->firstOrFail();
    $this->submission->update(['submitted_at' => now()->subDays(3)]);
    $this->viewer = viewer($this->hospital);
});

test('dashboard renders all widgets for a hospital user with a single form selected', function () {
    Livewire::actingAs($this->viewer)
        ->test(Dashboard::class)
        ->assertSee('77.7%')
        ->assertSee('1 evaluation')
        ->assertSee('Pick a survey above')
        ->set('form', $this->form->uuid)
        ->assertSee('Phone booking')
        ->assertSee('Waiting Area')
        ->assertSee('Were visible safety hazards present?')
        ->assertSee('Was the call answered within 3 rings?')
        ->assertSee('Ittefaq Hospital')
        ->assertDontSee('Viewing as admin');
});

test('dashboard period presets update the filters', function () {
    Livewire::actingAs($this->viewer)
        ->test(Dashboard::class)
        ->call('setPeriod', '30d')
        ->assertSet('period', '30d')
        ->assertSee('last 30 days')
        ->call('setPeriod', 'custom')
        ->set('from', now()->subYear()->toDateString())
        ->set('to', now()->toDateString())
        ->assertSee('1 evaluation');
});

test('admin opens any hospital dashboard with the admin banner', function () {
    $this->actingAs(superAdmin())
        ->get(route('hospitals.dashboard', $this->hospital))
        ->assertOk()
        ->assertSee('Viewing as admin')
        ->assertSee('77.7%');

    // hospital users cannot use the explicit-hospital route
    $this->actingAs($this->viewer)->get(route('hospitals.dashboard', $this->hospital))->assertForbidden();
    // super admins have no implicit hospital
    $this->actingAs(superAdmin())->get(route('dashboard'))->assertForbidden();
});

test('evaluations list filters, searches and sorts', function () {
    Submission::factory()->create(['form_id' => $this->form->id, 'form_version_id' => $this->form->current_version_id, 'hospital_id' => $this->hospital->id, 'submitted_at' => now()->subDay(), 'overall_pct' => 30, 'rag' => 'red', 'info' => ['facility' => 'Other Place', 'doctor' => 'Dr Zed']]);

    Livewire::actingAs($this->viewer)
        ->test(Index::class)
        ->assertSee('Ittefaq Hospital')
        ->assertSee('Other Place')
        ->assertSee('Dr. Farhan Siddiqui')
        ->set('rag', 'amber')
        ->assertSee('Dr. Farhan Siddiqui')
        ->assertDontSee('Other Place')
        ->set('rag', '')
        ->set('search', 'zed')
        ->assertSee('Other Place')
        ->assertDontSee('Dr. Farhan Siddiqui')
        ->set('search', '')
        ->call('sortBy', 'score')
        ->assertSet('sort', 'score')
        ->assertSet('direction', 'desc')
        ->call('sortBy', 'score')
        ->assertSet('direction', 'asc')
        ->assertDontSee('Status');
});

test('evaluation detail shows header, journey strip, sections and answers; admin tools only for admins', function () {
    Livewire::actingAs($this->viewer)
        ->test(Show::class, ['evaluation' => $this->submission])
        ->assertSee('Ittefaq Hospital')
        ->assertSee('Dr. Farhan Siddiqui')
        ->assertSee('Phone call')
        ->assertSee('77.7%')
        ->assertSee('Journey')
        ->assertSee('Phone booking')
        ->assertSee('Quick and friendly call.')
        ->assertSee('8 / 10')
        ->assertSee('receipt.jpg')
        ->assertDontSee('View raw payload')
        ->assertDontSee('Re-score this evaluation');

    Livewire::actingAs(superAdmin())
        ->test(Show::class, ['evaluation' => $this->submission])
        ->assertSee('View raw payload')
        ->assertSee('Mapping version')
        ->call('rescore')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('audit_logs', ['action' => 'submission.rescored', 'subject_id' => $this->submission->id]);
});

test('CSV export streams headers, info fields, overall and section columns for a single form', function () {
    $response = $this->actingAs($this->viewer)->get(route('evaluations.export', ['form' => $this->form->uuid]));

    $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    expect($response->headers->get('content-disposition'))->toContain('evaluations-'.$this->hospital->slug);

    $csv = $response->streamedContent();
    $lines = array_values(array_filter(explode("\n", trim(ltrim($csv, "\xEF\xBB\xBF")))));
    expect($lines)->toHaveCount(2);

    $header = str_getcsv($lines[0]);
    expect($header)->toContain('Submitted at', 'Facility', 'Doctor', 'Overall %', 'RAG', 'Section: Phone booking %', 'Section: Overall %', 'Section: Website/ App booking %')
        ->and(array_slice($header, 0, 11))->toBe(['Submitted at', 'Hospital', 'Form', 'Facility', 'Location', 'Visit date', 'Doctor', 'Booking method', 'Branch', 'Overall %', 'RAG']);

    $row = array_combine($header, str_getcsv($lines[1]));
    expect($row['Facility'])->toBe('Ittefaq Hospital')
        ->and($row['Doctor'])->toBe('Dr. Farhan Siddiqui')
        ->and($row['Booking method'])->toBe('Phone call')
        ->and($row['Branch'])->toBe('Main campus')
        ->and($row['Overall %'])->toBe('77.65')
        ->and($row['RAG'])->toBe('amber')
        ->and($row['Section: Phone booking %'])->toBe('85.00')
        ->and($row['Section: Overall %'])->toBe('91.67')
        ->and($row['Section: Website/ App booking %'])->toBe('')
        ->and($row['Evaluation URL'])->toContain('/evaluations/'.$this->submission->uuid);

    // all forms → overall only, no section columns
    $all = $this->actingAs($this->viewer)->get(route('evaluations.export'))->streamedContent();
    expect(str_getcsv(explode("\n", ltrim($all, "\xEF\xBB\xBF"))[0]))->not->toContain('Section: Phone booking %');
});

test('file proxy streams uploads with the connection token and never exposes the Typeform URL', function () {
    Http::fake(['https://api.typeform.com/responses/files/*' => Http::response('JPEGDATA', 200, ['Content-Type' => 'image/jpeg'])]);
    $answer = $this->submission->answers()->where('answer_type', 'file_url')->firstOrFail();

    $response = $this->actingAs($this->viewer)->get(route('evaluations.files.show', [$this->submission, $answer->id]));

    $response->assertOk()->assertHeader('content-type', 'image/jpeg');
    expect($response->headers->get('content-disposition'))->toStartWith('inline');
    expect($response->getContent())->toBe('JPEGDATA');
    Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Bearer '.$this->form->connection->access_token));

    // a non-file answer is 404
    $text = $this->submission->answers()->where('answer_type', 'text')->firstOrFail();
    $this->actingAs($this->viewer)->get(route('evaluations.files.show', [$this->submission, $text->id]))->assertNotFound();
});
