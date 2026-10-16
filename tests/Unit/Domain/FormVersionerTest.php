<?php

use App\Domain\Forms\FormVersioner;
use App\Domain\Typeform\FormStructureParser;
use App\Domain\Typeform\TypeformClient;
use App\Models\Form;
use App\Models\FormField;
use App\Models\Section;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Http::preventStrayRequests();
    $this->form = Form::factory()->create(['typeform_form_id' => 'rACXmOJ2']);
    $this->versioner = app(FormVersioner::class);
    $this->v1 = $this->versioner->createInitialVersion($this->form, fixture('form_definition_rACXmOJ2.json'));
});

test('initial version stores sections, fields and flags review', function () {
    expect($this->v1->version_number)->toBe(1)
        ->and($this->v1->needs_review)->toBeTrue()
        ->and($this->v1->sections()->count())->toBe(10)
        ->and($this->v1->fields()->count())->toBe(92)
        ->and($this->form->fresh()->current_version_id)->toBe($this->v1->id)
        ->and($this->form->fresh()->title)->toBe('Hospital Mystery Shopping Evaluation')
        ->and($this->v1->definition_hash)->toBe(FormStructureParser::hash(fixture('form_definition_rACXmOJ2.json')));

    $websiteQuestion = $this->v1->fields()->where('title', 'The website/ App was easy to access')->firstOrFail();
    expect($websiteQuestion->section->name)->toBe('General Information');
});

test('carry-over keeps admin renames, moves, scored flags and weights; flags new fields; drops removed ones', function () {
    // Admin edits on v1
    $general = $this->v1->sections()->where('name', 'General Information')->firstOrFail();
    $website = $this->v1->sections()->where('name', 'Website/ App booking')->firstOrFail();
    $arrival = $this->v1->sections()->where('name', 'Arrival & Reception')->firstOrFail();
    $arrival->update(['name' => 'Arrival', 'weight' => 2.0]);
    $website->update(['weight' => 0]);

    $websiteQuestion = FormField::query()->where('form_version_id', $this->v1->id)->where('title', 'The website/ App was easy to access')->firstOrFail();
    $websiteQuestion->update(['section_id' => $website->id]);

    $greeting = FormField::query()->where('form_version_id', $this->v1->id)->where('title', 'The agent greeted you politely')->firstOrFail();
    $greeting->update(['is_scored' => false, 'info_key' => null]);

    $facility = FormField::query()->where('form_version_id', $this->v1->id)->where('title', 'Healthcare Facility')->firstOrFail();
    $facility->update(['info_key' => 'branch']);

    // Admin-created section with a moved field
    $custom = Section::query()->create(['form_version_id' => $this->v1->id, 'name' => 'Custom', 'source_ref' => FormVersioner::ADMIN_SECTION_PREFIX.'abc', 'display_order' => 99, 'weight' => 1]);
    FormField::query()->where('form_version_id', $this->v1->id)->where('title', 'Value for money')->update(['section_id' => $custom->id]);

    // v2 definition: Section 4 renamed in Typeform, new field added, one field removed
    $v2def = fixture('form_definition_rACXmOJ2_v2.json');
    $v2def['fields'] = array_values(array_filter($v2def['fields'], fn ($f) => $f['title'] !== 'Hold time was acceptable'));

    $v2 = $this->versioner->createVersionFrom($this->form, $v2def, $this->v1);

    expect($v2->version_number)->toBe(2)->and($v2->needs_review)->toBeTrue();

    $sections = $v2->sections()->get()->keyBy('source_ref');
    expect($sections->get($arrival->source_ref)->name)->toBe('Arrival')       // admin rename wins over Typeform rename
        ->and((float) $sections->get($arrival->source_ref)->weight)->toBe(2.0)
        ->and((float) $sections->get($website->source_ref)->weight)->toBe(0.0)
        ->and($sections->has(FormVersioner::ADMIN_SECTION_PREFIX.'abc'))->toBeTrue();

    $fields = $v2->fields()->with('section')->get()->keyBy('title');
    expect($fields['The website/ App was easy to access']->section->source_ref)->toBe($website->source_ref)   // moved stays moved
        ->and($fields['The agent greeted you politely']->is_scored)->toBeFalse()                                 // unscored stays unscored
        ->and($fields['Healthcare Facility']->info_key)->toBe('branch')                                        // info key carried
        ->and($fields['Value for money']->section->name)->toBe('Custom')                                       // admin section carried
        ->and($fields['Was the discharge process explained?']->is_new_in_version)->toBeTrue()                  // new flagged
        ->and($fields['Was the discharge process explained?']->is_scored)->toBeTrue()
        ->and($fields->has('Hold time was acceptable'))->toBeFalse()                                           // removed disappears
        ->and($fields['Nurse introduced themselves']->is_new_in_version)->toBeFalse()
        ->and($fields['Nurse introduced themselves']->section->source_ref)->toBe($v2->sections()->where('name', 'Nursing Staff')->value('source_ref'));

    // Un-moved fields follow Typeform if Typeform moves them: scale comes from the new definition
    expect($fields['Was the discharge process explained?']->scale_max)->toBe(10);
});

test('scale min and max always come from the new definition', function () {
    $def = fixture('form_definition_rACXmOJ2.json');
    foreach ($def['fields'] as &$f) {
        if ($f['title'] === 'The agent greeted you politely') {
            $f['properties']['steps'] = 5;
            $f['properties']['start_at_one'] = true;
        }
    }
    unset($f);

    $v2 = $this->versioner->createVersionFrom($this->form, $def, $this->v1);
    $field = $v2->fields()->where('title', 'The agent greeted you politely')->firstOrFail();
    expect($field->scale_min)->toBe(1)->and($field->scale_max)->toBe(5)->and($v2->needs_review)->toBeFalse();
});

test('refreshFromTypeform creates a version only when the hash changes and refreshes the title', function () {
    $unchanged = fixture('form_definition_rACXmOJ2.json');
    $unchanged['title'] = 'Renamed in Typeform';
    $unchanged['logic'] = [];
    Http::fake(['*/forms/rACXmOJ2' => Http::sequence()
        ->push($unchanged)
        ->push(fixture('form_definition_rACXmOJ2_v2.json'))]);

    $result = $this->versioner->refreshFromTypeform($this->form, TypeformClient::for($this->form->connection));
    expect($result)->toBeNull()
        ->and($this->form->fresh()->title)->toBe('Renamed in Typeform')
        ->and($this->form->versions()->count())->toBe(1);

    $v2 = $this->versioner->refreshFromTypeform($this->form->fresh(), TypeformClient::for($this->form->connection));

    expect($v2)->not->toBeNull()
        ->and($v2->version_number)->toBe(2)
        ->and($this->form->fresh()->current_version_id)->toBe($v2->id)
        ->and($v2->needs_review)->toBeTrue();
    $this->assertDatabaseHas('audit_logs', ['action' => 'form.version_created', 'subject_id' => $this->form->id]);

    // existing submissions are not touched (none here); v1 remains intact
    expect($this->v1->fresh()->fields()->count())->toBe(92);
});
